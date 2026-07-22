<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">New release plan</h2>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <form method="POST" action="{{ route('releases.store') }}" class="card card-pad">
                @csrf
                @include('releases.form')
                <div class="mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('dashboard') }}" class="btn-ghost btn-sm">Cancel</a>
                    <button class="btn-primary">Create release</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
