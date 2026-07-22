@php
    $flashes = [
        ['key' => 'success', 'tone' => 'emerald', 'icon' => 'M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42l2.79 2.79 6.79-6.79a1 1 0 011.42 0z'],
        ['key' => 'error', 'tone' => 'rose', 'icon' => 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.7 7.3a1 1 0 00-1.4 1.4L8.6 10l-1.3 1.3a1 1 0 101.4 1.4L10 11.4l1.3 1.3a1 1 0 001.4-1.4L11.4 10l1.3-1.3a1 1 0 00-1.4-1.4L10 8.6 8.7 7.3z'],
        ['key' => 'overlap_warning', 'tone' => 'amber', 'icon' => 'M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.515 2.625H3.72c-1.344 0-2.187-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z'],
    ];
    $tones = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'rose' => 'border-rose-200 bg-rose-50 text-rose-800',
        'amber' => 'border-amber-300 bg-amber-50 text-amber-900',
    ];
    $iconTones = ['emerald' => 'text-emerald-500', 'rose' => 'text-rose-500', 'amber' => 'text-amber-500'];
@endphp

@foreach ($flashes as $f)
    @if (session($f['key']))
        <div x-data="{ show: true }" x-show="show" x-transition
             class="mt-4 flex animate-fade-in-up items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-card {{ $tones[$f['tone']] }}">
            <svg class="mt-0.5 h-5 w-5 flex-none {{ $iconTones[$f['tone']] }}" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="{{ $f['icon'] }}" clip-rule="evenodd" />
            </svg>
            <span class="flex-1">{{ session($f['key']) }}</span>
            <button type="button" @click="show = false" class="flex-none rounded p-0.5 opacity-60 transition hover:opacity-100" aria-label="Dismiss">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
            </button>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-card">
        <p class="font-medium">Please fix the following:</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
