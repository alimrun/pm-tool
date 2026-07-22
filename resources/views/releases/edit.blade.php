<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">Edit release</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('releases.update', $release) }}" class="card card-pad">
                @csrf @method('PUT')
                @include('releases.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('releases.show', $release) }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Save changes</button>
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
