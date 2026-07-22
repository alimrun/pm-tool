@php $isCreate = ! $user->exists; @endphp
<div class="space-y-6">
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
        <select id="role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (\App\Models\User::ROLES as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-400">Only Admins manage projects, teams and releases. All roles can comment and check tasks.</p>
        @error('role') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">
                {{ $isCreate ? 'Password' : 'New password' }}
                @unless ($isCreate) <span class="text-gray-400">(leave blank to keep)</span> @endunless
            </label>
            <input id="password" name="password" type="password" {{ $isCreate ? 'required' : '' }} autocomplete="new-password"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>
</div>
