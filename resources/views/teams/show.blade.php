<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $team->color }}"></span>
                <h2 class="page-title">{{ $team->name }}</h2>
                @if ($team->isArchived())
                    <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Archived</span>
                @endif
            </div>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('teams.edit', $team) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">
            @php
                $conflictCount = collect($conflicts)->filter()->count();
                $busyThrough = $releases->max('end_date');
            @endphp

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="card p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Releases</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $releases->count() }}</p>
                </div>
                <div class="card p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Booked through</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $busyThrough ? $busyThrough->format('M j, Y') : '—' }}</p>
                    @if ($busyThrough)
                        <p class="text-xs text-slate-500">Next free: {{ $busyThrough->copy()->addDay()->format('M j, Y') }}</p>
                    @endif
                </div>
                <div class="rounded-xl p-5 shadow {{ $conflictCount ? 'bg-amber-50' : 'bg-white' }}">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Overlapping releases</p>
                    <p class="mt-1 text-2xl font-semibold {{ $conflictCount ? 'text-amber-700' : 'text-slate-900' }}">{{ $conflictCount }}</p>
                </div>
            </div>

            @if ($team->description)
                <div class="card card-pad">
                    <p class="text-sm text-slate-600">{{ $team->description }}</p>
                </div>
            @endif

            {{-- Members --}}
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Members ({{ $team->members->count() }})</h3>
                </div>

                @if (auth()->user()->canManageTeamMembers())
                    <form method="POST" action="{{ route('teams.members.store', $team) }}"
                          class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-6 py-4">
                        @csrf
                        <div class="min-w-[220px] flex-1">
                            <label for="user_id" class="eyebrow">Add a member</label>
                            <select id="user_id" name="user_id" required class="field-input">
                                <option value="">— Select a person —</option>
                                @foreach ($assignableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->roleLabel() }}</option>
                                @endforeach
                            </select>
                            @error('user_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <button class="btn-primary btn-sm" @disabled($assignableUsers->isEmpty())>Add member</button>
                    </form>
                @endif

                @if ($team->members->isEmpty())
                    <div class="px-6 py-8 text-center text-sm text-slate-400">No members yet.</div>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($team->members as $member)
                            <li class="flex items-center gap-3 px-6 py-3">
                                <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                                    {{ strtoupper(\Illuminate\Support\Str::of($member->name)->explode(' ')->map(fn ($p) => $p[0] ?? '')->take(2)->implode('')) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-800">{{ $member->name }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ $member->email }}</p>
                                </div>
                                <span class="hidden sm:block">@include('partials.role-badge', ['role' => $member->role])</span>
                                @if (auth()->user()->canManageTeamMembers())
                                    <form method="POST" action="{{ route('teams.members.destroy', [$team, $member]) }}"
                                          data-confirm="Remove {{ $member->name }} from this team?" data-confirm-verb="Remove">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-slate-400 hover:text-rose-600">Remove</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Schedule (chronological)</h3>
                </div>
                @include('partials.releases-table', ['releases' => $releases, 'conflicts' => $conflicts, 'showProject' => true, 'showTeam' => false])
            </div>
        </div>
    </div>
</x-app-layout>
