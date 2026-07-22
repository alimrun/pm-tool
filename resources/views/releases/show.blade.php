<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $release->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    <a href="{{ route('projects.show', $release->project) }}" class="hover:text-indigo-600">
                        <span class="inline-block h-2.5 w-2.5 rounded-full align-middle" style="background-color: {{ $release->project->color }}"></span>
                        {{ $release->project->name }}
                    </a>
                    <span class="mx-1 text-gray-300">·</span>
                    <a href="{{ route('teams.show', $release->team) }}" class="hover:text-indigo-600">
                        <span class="inline-block h-2.5 w-2.5 rounded-full align-middle" style="background-color: {{ $release->team->color }}"></span>
                        {{ $release->team->name }}
                    </a>
                    <span class="mx-1 text-gray-300">·</span>
                    {{ $release->year }} {{ $release->quarterLabel() }}
                </p>
            </div>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('releases.edit', $release) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit release</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">

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

            @if ($release->description)
                <div class="rounded-xl bg-white p-6 shadow">
                    <h3 class="text-sm font-semibold text-gray-700">Description</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-600">{{ $release->description }}</p>
                </div>
            @endif

            {{-- Phase timeline + off-days --}}
            <div class="rounded-xl bg-white p-6 shadow">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-700">Timeline</h3>
                    <span class="text-sm text-gray-500">
                        {{ $release->start_date->format('M j, Y') }} – {{ $release->end_date->format('M j, Y') }}
                        · {{ $release->durationInDays() }} days
                        · <span class="font-medium text-gray-700">{{ $release->workingDays() }} working</span>
                        @if ($release->offDayCount()) · {{ $release->offDayCount() }} off @endif
                    </span>
                </div>

                @php $total = max($release->durationInDays(), 1); @endphp
                <div class="mt-4">
                    <div class="relative h-9 w-full overflow-hidden rounded-md bg-gray-100">
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
                        {{-- off-day ticks (drawn on top) --}}
                        @foreach ($release->offDays as $off)
                            @php $o = $release->start_date->diffInDays($off->date) / $total * 100; @endphp
                            <div class="absolute top-0 z-10 h-9 bg-gray-900/25"
                                 style="left: {{ $o }}%; width: {{ 1 / $total * 100 }}%"
                                 title="Off: {{ $off->date->format('M j, Y') }}{{ $off->reason ? ' — '.$off->reason : '' }}"></div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-4">
                    @foreach ($release->phases as $phase)
                        <div class="rounded-lg border border-gray-100 p-3">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ \App\Models\Release::PHASE_COLORS[$phase->phase] }}"></span>
                                <span class="text-xs font-semibold text-gray-700">{{ $phase->label() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ $phase->start_date->format('M j') }} – {{ $phase->end_date->format('M j, Y') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Off-days management --}}
            <div class="rounded-xl bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-700">Off-days ({{ $release->offDays->count() }})</h3>
                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('releases.offdays.weekends', $release) }}">
                            @csrf
                            <button class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Mark weekends off</button>
                        </form>
                    @endif
                </div>

                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('releases.offdays.store', $release) }}"
                          class="flex flex-wrap items-end gap-3 border-b border-gray-100 px-6 py-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Date</label>
                            <input type="date" name="date" required min="{{ $release->start_date->toDateString() }}" max="{{ $release->end_date->toDateString() }}"
                                   class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-500">Reason (optional)</label>
                            <input type="text" name="reason" placeholder="Holiday, team off-site…"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Mark off-day</button>
                    </form>
                @endif

                @if ($release->offDays->isEmpty())
                    <div class="px-6 py-6 text-center text-sm text-gray-400">No off-days marked. Working days = full window.</div>
                @else
                    <ul class="flex flex-wrap gap-2 px-6 py-4">
                        @foreach ($release->offDays as $off)
                            <li class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-700">
                                <span class="font-medium">{{ $off->date->format('D, M j') }}</span>
                                @if ($off->reason)<span class="text-gray-400">· {{ $off->reason }}</span>@endif
                                @if (auth()->user()->isAdmin())
                                    <form method="POST" action="{{ route('releases.offdays.destroy', [$release, $off]) }}">
                                        @csrf @method('DELETE')
                                        <button class="text-gray-400 hover:text-rose-600" title="Remove">✕</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Tasks --}}
            <div class="rounded-xl bg-white shadow">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-700">Tasks ({{ $release->rootTasks->count() }})</h3>
                </div>
                <div class="p-6">
                    @include('partials.tasks-panel', ['release' => $release, 'users' => $users])
                </div>
            </div>

            {{-- Comments --}}
            <div class="rounded-xl bg-white shadow">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-700">Comments ({{ $release->comments->count() }})</h3>
                </div>
                <div class="p-6">
                    @include('partials.comments', ['comments' => $release->comments, 'storeUrl' => route('releases.comments.store', $release)])
                </div>
            </div>

            {{-- Documents --}}
            <div class="rounded-xl bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-700">Documents ({{ $release->documents->count() }})</h3>
                </div>

                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('releases.documents.store', $release) }}" enctype="multipart/form-data"
                          class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-6 py-4">
                        @csrf
                        <input type="file" name="document" required
                               class="block text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Upload</button>
                        <span class="text-xs text-gray-400">Max 20 MB · pdf, doc(x), xls(x), ppt(x), txt, csv, png, jpg, zip</span>
                        @error('document') <p class="w-full text-sm text-rose-600">{{ $message }}</p> @enderror
                    </form>
                @endif

                @if ($release->documents->isEmpty())
                    <div class="px-6 py-8 text-center text-sm text-gray-500">No documents attached.</div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($release->documents as $document)
                            <li class="flex items-center justify-between px-6 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('releases.documents.download', [$release, $document]) }}" class="truncate text-sm font-medium text-gray-800 hover:text-indigo-600">{{ $document->original_name }}</a>
                                    <p class="text-xs text-gray-400">
                                        {{ $document->humanSize() }}
                                        @if ($document->uploader) · {{ $document->uploader->name }} @endif
                                        · {{ $document->created_at->format('M j, Y') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('releases.documents.download', [$release, $document]) }}" class="text-sm text-gray-500 hover:text-indigo-600">Download</a>
                                    @if (auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('releases.documents.destroy', [$release, $document]) }}"
                                              onsubmit="return confirm('Delete this document?')">
                                            @csrf @method('DELETE')
                                            <button class="text-sm text-gray-500 hover:text-rose-600">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- History --}}
            <div class="rounded-xl bg-white shadow" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-6 py-4 text-left">
                    <h3 class="text-sm font-semibold text-gray-700">History ({{ $history->count() }})</h3>
                    <span class="text-xs text-gray-400" x-text="open ? 'Hide' : 'Show'">Show</span>
                </button>
                <div x-show="open" x-cloak class="divide-y divide-gray-100 border-t border-gray-100 px-6 py-2">
                    @include('partials.activity-list', ['activities' => $history])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
