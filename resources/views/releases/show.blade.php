<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="page-title">{{ $release->name }}</h2>
                    @if ($release->isComplete())
                        <span class="badge bg-emerald-50 text-emerald-700">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.79 6.8-6.79a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                            Completed
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    <a href="{{ route('projects.show', $release->project) }}" class="hover:text-brand-600">
                        <span class="inline-block h-2.5 w-2.5 rounded-full align-middle" style="background-color: {{ $release->project->color }}"></span>
                        {{ $release->project->name }}
                    </a>
                    <span class="mx-1 text-slate-300">·</span>
                    <a href="{{ route('teams.show', $release->team) }}" class="hover:text-brand-600">
                        <span class="inline-block h-2.5 w-2.5 rounded-full align-middle" style="background-color: {{ $release->team->color }}"></span>
                        {{ $release->team->name }}
                    </a>
                    <span class="mx-1 text-slate-300">·</span>
                    {{ $release->year }} {{ $release->quarterLabel() }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('board.index', ['release_id' => $release->id]) }}" class="btn-secondary btn-sm">Board view</a>
                @if (auth()->user()->canManageReleases())
                    <a href="{{ route('releases.edit', $release) }}" class="btn-secondary btn-sm">Edit release</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">

            {{-- Full-width: conflict alert --}}
            @if ($conflicts->isNotEmpty())
                <div class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    <svg class="mt-0.5 h-5 w-5 flex-none text-amber-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.515 2.625H3.72c-1.344 0-2.187-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                    <div>
                        <p class="font-medium">Team {{ $release->team->name }} is double-booked during this window.</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($conflicts as $c)
                                <li><a href="{{ route('releases.show', $c) }}" class="underline">{{ $c->name }}</a> ({{ $c->start_date->format('M j') }} – {{ $c->end_date->format('M j, Y') }})</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- Full-width: phase timeline --}}
            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-700">Timeline</h3>
                    <span class="text-sm text-slate-500">
                        {{ $release->start_date->format('M j, Y') }} – {{ $release->end_date->format('M j, Y') }}
                        · {{ $release->durationInDays() }} days
                        · <span class="font-medium text-slate-700">{{ $release->workingDays() }} working</span>
                        @if ($release->offDayCount()) · {{ $release->offDayCount() }} off @endif
                    </span>
                </div>

                @php $total = max($release->durationInDays(), 1); @endphp
                <div class="mt-4">
                    <div class="relative h-9 w-full overflow-hidden rounded-md bg-slate-100">
                        @foreach ($release->phases as $phase)
                            @php
                                $offset = $release->start_date->diffInDays($phase->start_date) / $total * 100;
                                $width = ($phase->start_date->diffInDays($phase->end_date) + 1) / $total * 100;
                            @endphp
                            <div class="absolute top-0 flex h-9 items-center justify-center overflow-hidden text-[11px] font-medium text-white"
                                 style="left: {{ $offset }}%; width: {{ $width }}%; background-color: {{ \App\Models\Release::PHASE_COLORS[$phase->phase] }}"
                                 title="{{ $phase->label() }}: {{ $phase->start_date->format('M j') }} – {{ $phase->end_date->format('M j') }}">
                                <span class="truncate px-1">{{ $phase->label() }}</span>
                            </div>
                        @endforeach
                        @foreach ($release->offDays as $off)
                            @php $o = $release->start_date->diffInDays($off->date) / $total * 100; @endphp
                            <div class="absolute top-0 z-10 h-9 bg-slate-900/25"
                                 style="left: {{ $o }}%; width: {{ 1 / $total * 100 }}%"
                                 title="Off: {{ $off->date->format('M j, Y') }}{{ $off->reason ? ' — '.$off->reason : '' }}"></div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($release->phases as $phase)
                        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ \App\Models\Release::PHASE_COLORS[$phase->phase] }}"></span>
                                <span class="text-xs font-semibold text-slate-700">{{ $phase->label() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ $phase->start_date->format('M j') }} – {{ $phase->end_date->format('M j, Y') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Main + sidebar --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- ============ MAIN COLUMN ============ --}}
                <div class="space-y-6 lg:col-span-2">
                    @if ($release->description)
                        <div class="card card-pad">
                            <h3 class="text-sm font-semibold text-slate-700">Description</h3>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $release->description }}</p>
                        </div>
                    @endif

                    {{-- Tasks --}}
                    <div class="card">
                        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">Tasks ({{ $release->rootTasks->count() }})</h3>
                            <a href="{{ route('board.index', ['release_id' => $release->id]) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h3a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm6 0a1 1 0 011-1h3a1 1 0 011 1v7a1 1 0 01-1 1h-3a1 1 0 01-1-1V4z"/></svg>
                                Board view
                            </a>
                        </div>
                        <div class="p-5 sm:p-6">
                            @include('partials.tasks-panel', ['release' => $release, 'users' => $users])
                        </div>
                    </div>

                    {{-- Comments --}}
                    <div class="card">
                        <div class="border-b border-slate-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">Comments ({{ $release->comments->count() }})</h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            @include('partials.comments', ['comments' => $release->comments, 'storeUrl' => route('releases.comments.store', $release)])
                        </div>
                    </div>
                </div>

                {{-- ============ SIDEBAR ============ --}}
                <div class="space-y-6 lg:col-span-1">

                    {{-- Details --}}
                    <div class="card card-pad">
                        <h3 class="text-sm font-semibold text-slate-700">Details</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Status</dt>
                                <dd>
                                    @if ($release->isComplete())
                                        <span class="badge bg-emerald-50 text-emerald-700">Completed</span>
                                    @else
                                        <span class="badge bg-brand-50 text-brand-700">Ongoing</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Project</dt>
                                <dd class="inline-flex items-center gap-1.5 font-medium text-slate-800">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $release->project->color }}"></span>{{ $release->project->name }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Team</dt>
                                <dd class="inline-flex items-center gap-1.5 font-medium text-slate-800">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $release->team->color }}"></span>{{ $release->team->name }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Quarter</dt>
                                <dd class="font-medium text-slate-800">{{ $release->year }} · {{ $release->quarterLabel() }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Window</dt>
                                <dd class="text-right font-medium text-slate-800">{{ $release->start_date->format('M j') }} – {{ $release->end_date->format('M j, Y') }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500">Working days</dt>
                                <dd class="font-medium text-slate-800">{{ $release->workingDays() }}<span class="font-normal text-slate-400"> / {{ $release->durationInDays() }}</span></dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Members --}}
                    <div class="card">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">Members ({{ $release->members->count() }})</h3>
                            @if (auth()->user()->canManageReleases())
                                <a href="{{ route('releases.edit', $release) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Edit</a>
                            @endif
                        </div>
                        @if ($release->members->isEmpty())
                            <div class="px-5 py-6 text-center text-sm text-slate-400">No members assigned.</div>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($release->members as $member)
                                    <li class="flex items-center gap-3 px-5 py-3">
                                        <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                                            {{ strtoupper(\Illuminate\Support\Str::of($member->name)->explode(' ')->map(fn ($p) => $p[0] ?? '')->take(2)->implode('')) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-800">{{ $member->name }}</p>
                                            <p class="truncate text-xs text-slate-400">{{ $member->roleLabel() }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Completion --}}
                    @if ($release->isComplete())
                        <div class="card overflow-hidden">
                            <div class="flex items-center gap-3 border-b border-slate-100 bg-emerald-50/50 px-5 py-4">
                                <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0l-3.5-3.5a1 1 0 111.4-1.4l2.8 2.79 6.8-6.79a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                                </span>
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-slate-900">Completed</h3>
                                    <p class="text-xs text-slate-500">
                                        {{ $release->completed_at->format('M j, Y') }}
                                        @if ($release->completedBy) · by {{ $release->completedBy->name }} @endif
                                    </p>
                                </div>
                                @if (auth()->user()->canManageReleases())
                                    <form method="POST" action="{{ route('releases.reopen', $release) }}">
                                        @csrf
                                        <button class="btn-secondary btn-sm">Reopen</button>
                                    </form>
                                @endif
                            </div>
                            @if ($release->renderedCompletionNotes())
                                <div class="prose-notes px-5 py-4 text-sm text-slate-700">{!! $release->renderedCompletionNotes() !!}</div>
                            @endif
                        </div>
                    @elseif (auth()->user()->canManageReleases())
                        <div class="card card-pad" x-data="{ open: false }">
                            <h3 class="text-sm font-semibold text-slate-900">Mark complete</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Drops it off the dashboard and stops it counting against the team's schedule. Reopen anytime.</p>
                            <button type="button" @click="open = !open" x-show="!open" class="btn-primary btn-sm mt-3 w-full">Mark complete</button>
                            <form x-show="open" x-cloak method="POST" action="{{ route('releases.complete', $release) }}" class="mt-3">
                                @csrf
                                <label for="completion_notes" class="field-label">Notes <span class="text-slate-400">(optional · Markdown)</span></label>
                                <textarea id="completion_notes" name="completion_notes" rows="4" class="field-textarea" placeholder="What shipped, known issues, follow-ups…"></textarea>
                                <div class="mt-3 flex items-center gap-2">
                                    <button class="btn-primary btn-sm">Confirm</button>
                                    <button type="button" @click="open = false" class="btn-ghost btn-sm">Cancel</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    {{-- Off-days --}}
                    <div class="card">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">Off-days ({{ $release->offDays->count() }})</h3>
                            @if (auth()->user()->canManageReleases())
                                <form method="POST" action="{{ route('releases.offdays.weekends', $release) }}">
                                    @csrf
                                    <button class="text-xs font-medium text-brand-600 hover:text-brand-700">Mark weekends</button>
                                </form>
                            @endif
                        </div>

                        @if (auth()->user()->canManageReleases())
                            <form method="POST" action="{{ route('releases.offdays.store', $release) }}" class="space-y-3 border-b border-slate-100 px-5 py-4">
                                @csrf
                                <div>
                                    <label class="eyebrow">Date</label>
                                    <input type="date" name="date" required min="{{ $release->start_date->toDateString() }}" max="{{ $release->end_date->toDateString() }}" class="field-input">
                                </div>
                                <div>
                                    <label class="eyebrow">Reason (optional)</label>
                                    <input type="text" name="reason" placeholder="Holiday, off-site…" class="field-input">
                                </div>
                                <button class="btn-primary btn-sm w-full">Mark off-day</button>
                            </form>
                        @endif

                        @if ($release->offDays->isEmpty())
                            <div class="px-5 py-6 text-center text-sm text-slate-400">No off-days marked.</div>
                        @else
                            <ul class="flex flex-wrap gap-2 px-5 py-4">
                                @foreach ($release->offDays as $off)
                                    <li class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                                        <span class="font-medium">{{ $off->date->format('D, M j') }}</span>
                                        @if ($off->reason)<span class="text-slate-400">· {{ $off->reason }}</span>@endif
                                        @if (auth()->user()->canManageReleases())
                                            <form method="POST" action="{{ route('releases.offdays.destroy', [$release, $off]) }}">
                                                @csrf @method('DELETE')
                                                <button class="text-slate-400 hover:text-rose-600" title="Remove">✕</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Documents --}}
                    <div class="card">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-slate-700">Documents ({{ $release->documents->count() }})</h3>
                        </div>

                        @if (auth()->user()->canManageReleases())
                            <form method="POST" action="{{ route('releases.documents.store', $release) }}" enctype="multipart/form-data" class="space-y-2 border-b border-slate-100 px-5 py-4">
                                @csrf
                                <input type="file" name="document" required
                                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs text-slate-400">Max 20 MB</span>
                                    <button class="btn-primary btn-sm">Upload</button>
                                </div>
                                @error('document') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                            </form>
                        @endif

                        @if ($release->documents->isEmpty())
                            <div class="px-5 py-6 text-center text-sm text-slate-400">No documents attached.</div>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach ($release->documents as $document)
                                    <li class="flex items-center justify-between gap-2 px-5 py-3">
                                        <div class="min-w-0">
                                            <a href="{{ route('releases.documents.download', [$release, $document]) }}" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-600">{{ $document->original_name }}</a>
                                            <p class="text-xs text-slate-400">{{ $document->humanSize() }} · {{ $document->created_at->format('M j, Y') }}</p>
                                        </div>
                                        @if (auth()->user()->canManageReleases())
                                            <form method="POST" action="{{ route('releases.documents.destroy', [$release, $document]) }}"
                                                  data-confirm="Delete this document?" data-confirm-verb="Delete">
                                                @csrf @method('DELETE')
                                                <button class="flex-none text-xs text-slate-400 hover:text-rose-600">Delete</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- History --}}
                    <div class="card" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-5 py-4 text-left">
                            <h3 class="text-sm font-semibold text-slate-700">History ({{ $history->count() }})</h3>
                            <span class="text-xs text-slate-400" x-text="open ? 'Hide' : 'Show'">Show</span>
                        </button>
                        <div x-show="open" x-cloak class="border-t border-slate-100 px-5 py-2">
                            @include('partials.activity-list', ['activities' => $history])
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
