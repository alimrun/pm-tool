<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Activity</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" action="{{ route('activity.index') }}" class="rounded-xl bg-white p-4 shadow">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">User</label>
                        <select name="causer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Everyone</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected($filters['causer_id'] === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Event</label>
                        <select name="event" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All events</option>
                            @foreach (['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'] as $val => $label)
                                <option value="{{ $val }}" @selected($filters['event'] === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
                        <a href="{{ route('activity.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Reset</a>
                    </div>
                </div>
            </form>

            <div class="rounded-xl bg-white shadow">
                <div class="divide-y divide-gray-100 px-6 py-2">
                    @include('partials.activity-list', ['activities' => $activities])
                </div>
            </div>

            <div>{{ $activities->links() }}</div>
        </div>
    </div>
</x-app-layout>
