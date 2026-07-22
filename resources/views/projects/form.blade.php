<div class="space-y-6">
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $project->name) }}" required
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
        <textarea id="description" name="description" rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $project->description) }}</textarea>
        @error('description') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="color" class="block text-sm font-medium text-gray-700">Color</label>
        <div class="mt-1 flex items-center gap-3">
            <input id="color" name="color" type="color" value="{{ old('color', $project->color ?? '#4f46e5') }}"
                   class="h-10 w-16 cursor-pointer rounded border border-gray-300">
            <span class="text-sm text-gray-500">Used to distinguish this project on the dashboard.</span>
        </div>
        @error('color') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
</div>
