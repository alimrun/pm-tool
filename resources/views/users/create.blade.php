<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">New user</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('users.store') }}" class="rounded-xl bg-white p-6 shadow">
                @csrf
                @include('users.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create user</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
