<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function store(TaskRequest $request, Release $release): RedirectResponse
    {
        $release->tasks()->create($this->attributes($request, [
            'parent_id' => null,
            'position' => $release->rootTasks()->count(),
        ]));

        return back()->with('success', 'Task added.');
    }

    public function storeSubtask(TaskRequest $request, Task $task): RedirectResponse
    {
        // One level of nesting only.
        if ($task->isSubtask()) {
            return back()->with('error', 'A subtask cannot have its own subtasks.');
        }

        $task->subtasks()->create($this->attributes($request, [
            'release_id' => $task->release_id,
            'parent_id' => $task->id,
            'position' => $task->subtasks()->count(),
        ]));

        return back()->with('success', 'Subtask added.');
    }

    public function show(Task $task): View
    {
        $task->load(['release.project', 'release.team', 'assignee', 'creator',
            'subtasks.assignee', 'comments.user', 'parent']);

        // Assignees are team-wise: the release team's active members, plus any
        // current assignee who has since left the team (so nothing displays blank).
        $users = $task->release->team->members()->active()->orderBy('name')->get();
        $current = collect([$task->assignee])
            ->merge($task->subtasks->map->assignee)
            ->filter();
        $users = $users->concat($current)->unique('id')->sortBy('name')->values();

        return view('tasks.show', [
            'task' => $task,
            'users' => $users,
        ]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($this->attributes($request));

        return back()->with('success', 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $task->update($data);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', $task->isSubtask() ? 'Subtask deleted.' : 'Task deleted.');
    }

    /**
     * Build the writable attributes from the request, merged with defaults.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function attributes(TaskRequest $request, array $extra = []): array
    {
        $validated = $request->safe()->only(['title', 'description', 'status', 'assignee_id', 'due_date', 'phase']);
        $validated['status'] = $validated['status'] ?? 'todo';

        // created_by is only set on creation (when a position/parent is provided).
        if (array_key_exists('position', $extra)) {
            $extra['created_by'] = $request->user()->id;
        }

        return array_merge($validated, $extra);
    }
}
