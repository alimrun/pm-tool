<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit release</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('releases.update', $release) }}" class="rounded-xl bg-white p-6 shadow">
                @csrf @method('PUT')
                @include('releases.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('releases.show', $release) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save changes</button>
                </div>
            </form>

            <div class="mt-4 flex justify-end">
                <form method="POST" action="{{ route('releases.destroy', $release) }}"
                      onsubmit="return confirm('Delete this release, its phases and documents?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-rose-600 hover:text-rose-800">Delete this release</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
