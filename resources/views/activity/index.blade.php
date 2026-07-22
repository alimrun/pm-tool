<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Activity</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">

            {{-- Filters --}}
            <form method="GET" action="{{ route('activity.index') }}" class="card p-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">User</label>
                        <select name="causer_id" class="field-input">
                            <option value="">Everyone</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($filters['causer_id'] === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Event</label>
                        <select name="event" class="field-input">
                            <option value="">All events</option>
                            @foreach (['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'] as $val => $label)
                                <option value="{{ $val }}" @selected($filters['event'] === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="btn-primary btn-sm">Filter</button>
                        <a href="{{ route('activity.index') }}" class="btn-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>

            <div class="card">
                <div class="divide-y divide-slate-100 px-6 py-2">
                    @include('partials.activity-list', ['activities' => $activities])
                </div>
            </div>

            <div>{{ $activities->links() }}</div>
        </div>
    </div>
</x-app-layout>
