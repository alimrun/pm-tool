@php
    /** @var \App\Models\User $rowUser */
    /** @var \App\Models\TasksheetEntry|null $entry */
    $viewer = auth()->user();
    $isLeadViewer = $viewer->isLead();
    $canEdit = $viewer->id === $rowUser->id || $isLeadViewer;
    $taskCols = 7; // plan, result, comment, work points, tickets, ticket count, ticket points
@endphp
<tbody x-data="{ editing: false }" class="divide-y divide-slate-100 border-t border-slate-100">
    <tr x-show="!editing" class="align-top">
        <td class="px-3 py-3">
            <div class="font-medium text-slate-800">{{ $rowUser->name }}</div>
            <div class="text-xs text-slate-400">{{ $rowUser->roleLabel() }}</div>
            @if ($entry?->wasFilledLate())
                <div class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700"
                     title="This row was filled in after the day had passed">Not added on the operating day</div>
            @endif
        </td>

        @if ($entry && $entry->isOnLeave())
            <td colspan="{{ $taskCols }}" class="px-3 py-3">
                <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700">{{ $entry->leaveLabel() }}</span>
            </td>
        @elseif ($entry)
            <td class="max-w-xs whitespace-pre-line px-3 py-3 text-slate-700">{{ $entry->plan ?? '—' }}</td>
            <td class="max-w-xs whitespace-pre-line px-3 py-3 text-slate-700">{{ $entry->result ?? '—' }}</td>
            <td class="max-w-xs whitespace-pre-line px-3 py-3 text-slate-600">{{ $entry->comment ?? '—' }}</td>
            <td class="px-3 py-3 text-center text-slate-700">{{ $entry->work_points ?? '—' }}</td>
            <td class="max-w-[10rem] whitespace-pre-line px-3 py-3 text-slate-600">{{ $entry->tickets ?? '—' }}</td>
            <td class="px-3 py-3 text-center text-slate-700">{{ $entry->ticket_count ?? '—' }}</td>
            <td class="px-3 py-3 text-center text-slate-700">{{ $entry->ticket_points ?? '—' }}</td>
        @elseif ($isPast)
            <td colspan="{{ $taskCols }}" class="px-3 py-3">
                <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-600">Absent — not filled</span>
            </td>
        @else
            <td colspan="{{ $taskCols }}" class="px-3 py-3 text-sm text-slate-300">Not filled yet</td>
        @endif

        @if ($isLeadViewer)
            <td class="max-w-xs whitespace-pre-line px-3 py-3 text-slate-600">{{ $entry?->feedback ?? '—' }}</td>
        @endif

        <td class="px-3 py-3 text-right">
            @if ($canEdit)
                <button type="button" @click="editing = true" class="btn-secondary btn-sm">Edit</button>
            @endif
        </td>
    </tr>

    @if ($canEdit)
        <tr x-show="editing" style="display: none;">
            <td colspan="{{ 2 + $taskCols + ($isLeadViewer ? 1 : 0) }}" class="bg-slate-50 px-4 py-4">
                <form method="POST" action="{{ route('tasksheet.entries.upsert') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="team_id" value="{{ $team->id }}">
                    <input type="hidden" name="user_id" value="{{ $rowUser->id }}">
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-semibold text-slate-800">{{ $rowUser->name }} — {{ $day->format('M j, Y') }}</span>
                        <select name="leave_type" aria-label="Attendance"
                                class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="">Working</option>
                            @foreach (\App\Models\TasksheetEntry::LEAVE_TYPES as $val => $label)
                                <option value="{{ $val }}" @selected($entry?->leave_type === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-slate-400">Choosing a leave clears the day's task fields.</span>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Task plan at morning</label>
                            <textarea name="plan" rows="3" class="field-input">{{ $entry?->plan }}</textarea>
                        </div>
                        <div>
                            <label class="field-label">Day end result</label>
                            <textarea name="result" rows="3" class="field-input">{{ $entry?->result }}</textarea>
                        </div>
                        <div>
                            <label class="field-label">Comment</label>
                            <textarea name="comment" rows="3" class="field-input">{{ $entry?->comment }}</textarea>
                        </div>
                        <div>
                            <label class="field-label">Tickets</label>
                            <textarea name="tickets" rows="3" class="field-input">{{ $entry?->tickets }}</textarea>
                        </div>
                    </div>

                    <div class="grid max-w-md grid-cols-3 gap-3">
                        <div>
                            <label class="field-label">Work points</label>
                            <input type="number" name="work_points" min="0" value="{{ $entry?->work_points }}" class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Ticket count</label>
                            <input type="number" name="ticket_count" min="0" value="{{ $entry?->ticket_count }}" class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Ticket points</label>
                            <input type="number" name="ticket_points" min="0" value="{{ $entry?->ticket_points }}" class="field-input">
                        </div>
                    </div>

                    @if ($isLeadViewer)
                        <div>
                            <label class="field-label">Feedback <span class="font-normal text-slate-400">(visible to leads only)</span></label>
                            <textarea name="feedback" rows="2" class="field-input">{{ $entry?->feedback }}</textarea>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                        <button class="btn-primary btn-sm">Save row</button>
                    </div>
                </form>
            </td>
        </tr>
    @endif
</tbody>
