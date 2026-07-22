@php
    $role = $role ?? 'viewer';
    $label = \App\Models\User::ROLES[$role] ?? ucfirst($role);
    // Literal classes so Tailwind keeps them.
    $classes = match ($role) {
        'admin' => 'bg-indigo-100 text-indigo-700',
        'cto' => 'bg-purple-100 text-purple-700',
        'team_lead' => 'bg-cyan-100 text-cyan-700',
        'developer' => 'bg-blue-100 text-blue-700',
        'qa' => 'bg-amber-100 text-amber-800',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $classes }}">{{ $label }}</span>
