@php
    $user = auth()->user();
    $nav = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'patterns' => ['dashboard']],
        ['label' => 'Releases', 'route' => 'releases.index', 'patterns' => ['releases.*']],
        ['label' => 'Board', 'route' => 'board.index', 'patterns' => ['board.*']],
        ['label' => 'Calendar', 'route' => 'calendar.index', 'patterns' => ['calendar.*', 'events.*']],
        ['label' => 'Notes', 'route' => 'notes.index', 'patterns' => ['notes.*']],
        ['label' => 'Projects', 'route' => 'projects.index', 'patterns' => ['projects.*']],
        ['label' => 'Teams', 'route' => 'teams.index', 'patterns' => ['teams.*']],
        ['label' => 'Activity', 'route' => 'activity.index', 'patterns' => ['activity.*']],
    ];
    if ($user->canManageUsers()) {
        $nav[] = ['label' => 'Users', 'route' => 'users.index', 'patterns' => ['users.*']];
    }
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp

<nav x-data="{ open: false }" class="app-container">
    <div class="flex h-16 items-center justify-between gap-4">
        {{-- Left: brand + primary nav --}}
        <div class="flex items-center gap-6">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white shadow-sm">RP</span>
                <span class="hidden text-sm font-semibold tracking-tight text-slate-900 sm:inline">Release Planner</span>
            </a>

            <div class="hidden items-center gap-0.5 lg:flex">
                @foreach ($nav as $item)
                    @php $active = request()->routeIs(...$item['patterns']); @endphp
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                           'bg-brand-50 text-brand-700' => $active,
                           'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! $active,
                       ])>{{ $item['label'] }}</a>
                @endforeach
            </div>
        </div>

        {{-- Right: actions + user menu --}}
        <div class="flex items-center gap-3">
            @if ($user->isAdmin())
                <a href="{{ route('releases.create') }}" class="btn-primary btn-sm hidden sm:inline-flex">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/></svg>
                    New release
                </a>
            @endif

            <div class="hidden sm:block">@include('partials.role-badge', ['role' => $user->role])</div>

            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="flex items-center gap-2 rounded-lg px-1.5 py-1 text-sm text-slate-600 transition hover:bg-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ strtoupper($initials) }}</span>
                        <span class="hidden font-medium text-slate-700 md:inline">{{ $user->name }}</span>
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <p class="text-sm font-medium text-slate-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                    <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>

            {{-- Hamburger --}}
            <button @click="open = ! open" class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div x-show="open" x-cloak class="border-t border-slate-100 py-2 lg:hidden">
        <div class="space-y-1">
            @foreach ($nav as $item)
                @php $active = request()->routeIs(...$item['patterns']); @endphp
                <a href="{{ route($item['route']) }}"
                   @class([
                       'block rounded-lg px-3 py-2 text-base font-medium',
                       'bg-brand-50 text-brand-700' => $active,
                       'text-slate-600 hover:bg-slate-100' => ! $active,
                   ])>{{ $item['label'] }}</a>
            @endforeach
            @if ($user->isAdmin())
                <a href="{{ route('releases.create') }}" class="block rounded-lg px-3 py-2 text-base font-medium text-brand-700 hover:bg-brand-50">+ New release</a>
            @endif
        </div>
    </div>
</nav>
