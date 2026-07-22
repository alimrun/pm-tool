@php use App\Models\Task; use App\Models\Release; @endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs text-gray-400">
                <a href="{{ route('releases.show', $task->release) }}" class="hover:text-indigo-600">{{ $task->release->name }}</a>
                @if ($task->isSubtask() && $task->parent)
                    <span class="mx-1">/</span>
                    <a href="{{ route('tasks.show', $task->parent) }}" class="hover:text-indigo-600">{{ $task->parent->title }}</a>
                @endif
            </p>
            <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-800">
                {{ $task->title }}
                @if ($task->isSubtask())<span class="ml-2 align-middle text-xs font-normal text-gray-400">(subtask)</span>@endif
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Edit task --}}
            <div class="rounded-xl bg-white p-6 shadow">
                <form method="POST" action="{{ route('tasks.update', $task) }}" class="space-y-5">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Title</label>
                        <input name="title" value="{{ old('title', $task->title) }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('title') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $task->description) }}</textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @foreach (Task::STATUSES as $val => $label)
                                    <option value="{{ $val }}" @selected(old('status', $task->status) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Assignee</label>
                            <select name="assignee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Unassigned</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected((int) old('assignee_id', $task->assignee_id) === $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Due date</label>
                            <input type="date" name="due_date" value="{{ old('due_date', optional($task->due_date)->toDateString()) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phase</label>
                            <select name="phase" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">No phase</option>
                                @foreach (Release::PHASES as $val => $label)
                                    <option value="{{ $val }}" @selected(old('phase', $task->phase) === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-400">
                            @if ($task->creator) Created by {{ $task->creator->name }} · @endif
                            {{ $task->created_at->diffForHumans() }}
                        </p>
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save task</button>
                    </div>
                </form>
            </div>

            {{-- Subtasks --}}
            @unless ($task->isSubtask())
                <div class="rounded-xl bg-white p-6 shadow">
                    <h3 class="text-sm font-semibold text-gray-700">Subtasks ({{ $task->subtasks->count() }})</h3>

                    @if ($task->subtasks->isNotEmpty())
                        <ul class="mt-3 space-y-1">
                            @foreach ($task->subtasks as $sub)
                                <li class="flex items-center justify-between gap-2 rounded-md border border-gray-100 px-3 py-2">
                                    <a href="{{ route('tasks.show', $sub) }}" class="text-sm text-gray-700 hover:text-indigo-600 {{ $sub->isDone() ? 'text-gray-400 line-through' : '' }}">{{ $sub->title }}</a>
                                    <div class="flex items-center gap-2">
                                        @if ($sub->assignee)<span class="text-xs text-gray-400">{{ $sub->assignee->name }}</span>@endif
                                        @include('partials.status-badge', ['status' => $sub->status])
                                        <form method="POST" action="{{ route('tasks.destroy', $sub) }}" onsubmit="return confirm('Delete this subtask?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-gray-300 hover:text-rose-600">✕</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('tasks.subtasks.store', $task) }}" class="mt-3 flex gap-2">
                        @csrf
                        <input name="title" required placeholder="Add a subtask…"
                               class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-900">Add subtask</button>
                    </form>
                </div>
            @endunless

            {{-- Comments --}}
            <div class="rounded-xl bg-white p-6 shadow">
                <h3 class="text-sm font-semibold text-gray-700">Comments ({{ $task->comments->count() }})</h3>
                <div class="mt-4">
                    @include('partials.comments', ['comments' => $task->comments, 'storeUrl' => route('tasks.comments.store', $task)])
                </div>
            </div>

            <div>
                <a href="{{ route('releases.show', $task->release) }}" class="text-sm text-gray-500 hover:text-indigo-600">← Back to release</a>
            </div>
        </div>
    </div>
</x-app-layout>
