<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TaskRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Tasks and subtasks. Open to every authenticated user — collaboration is not
 * gated by the planning roles.
 */
class TaskController extends ApiController
{
    /**
     * Tasks across releases, filterable. Defaults to top-level tasks only,
     * since subtasks travel with their parent; `include_subtasks=1` flattens
     * them in for a client that wants everything.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Task::query()
            ->with(['release.project', 'assignee', 'creator'])
            ->withCount(['comments', 'subtasks'])
            ->unless($request->boolean('include_subtasks'), fn ($q) => $q->whereNull('parent_id'))
            ->when($this->filterId($request, 'release_id'), fn ($q, $id) => $q->where('release_id', $id))
            ->when($this->filterId($request, 'assignee_id'), fn ($q, $id) => $q->where('assignee_id', $id))
            ->when($this->filterId($request, 'parent_id'), fn ($q, $id) => $q->where('parent_id', $id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('phase'), fn ($q) => $q->where('phase', $request->input('phase')))
            ->when($request->filled('due_before'), fn ($q) => $q->whereDate('due_date', '<=', $request->date('due_before')))
            ->when($request->filled('due_after'), fn ($q) => $q->whereDate('due_date', '>=', $request->date('due_after')))
            ->when($request->boolean('overdue'), fn ($q) => $q
                ->whereNotIn('status', Task::DONE_STATUSES)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString()))
            ->orderBy('position')
            ->orderBy('id');

        return $this->paginate($request, $query, TaskResource::class);
    }

    /**
     * One release's task tree. Top-level tasks with their subtasks eager-loaded
     * — the shape a release detail screen draws, without a second round trip.
     */
    public function indexForRelease(Request $request, Release $release): AnonymousResourceCollection
    {
        $query = $release->rootTasks()
            ->with(['assignee', 'creator', 'subtasks.assignee'])
            ->withCount(['comments', 'subtasks'])
            ->when($this->filterId($request, 'assignee_id'), fn ($q, $id) => $q->where('assignee_id', $id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('phase'), fn ($q) => $q->where('phase', $request->input('phase')));

        return $this->paginate($request, $query, TaskResource::class);
    }

    public function show(Task $task): JsonResponse
    {
        $task->load([
            'release.project', 'release.team', 'assignee', 'creator',
            'parent', 'subtasks.assignee', 'comments.user',
        ])->loadCount(['comments', 'subtasks']);

        return $this->ok(new TaskResource($task));
    }

    public function store(TaskRequest $request, Release $release): JsonResponse
    {
        $task = $release->tasks()->create($this->attributes($request, [
            'parent_id' => null,
            'position' => $release->rootTasks()->count(),
        ]));

        return $this->created($this->hydrate($task), 'Task added.');
    }

    /** One level of nesting only — a subtask may not have subtasks of its own. */
    public function storeSubtask(TaskRequest $request, Task $task): JsonResponse
    {
        abort_if($task->isSubtask(), 422, 'A subtask cannot have its own subtasks.');

        $subtask = $task->subtasks()->create($this->attributes($request, [
            'release_id' => $task->release_id,
            'parent_id' => $task->id,
            'position' => $task->subtasks()->count(),
        ]));

        return $this->created($this->hydrate($subtask), 'Subtask added.');
    }

    public function update(TaskRequest $request, Task $task): JsonResponse
    {
        $task->update($this->attributes($request));

        return $this->ok($this->hydrate($task), 'Task updated.');
    }

    /** The lightweight status change, for a checkbox or a context menu. */
    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $task->update($data);

        return $this->ok($this->hydrate($task), 'Status updated.');
    }

    public function destroy(Task $task): JsonResponse
    {
        $wasSubtask = $task->isSubtask();
        $task->delete(); // the model's deleting hook removes subtasks + comments

        return $this->message($wasSubtask ? 'Subtask deleted.' : 'Task deleted.');
    }

    private function hydrate(Task $task): TaskResource
    {
        return new TaskResource(
            $task->load(['assignee', 'creator', 'subtasks.assignee', 'release'])
                ->loadCount(['comments', 'subtasks'])
        );
    }

    /**
     * Writable attributes merged with defaults. `created_by` is stamped only on
     * creation, which is signalled by the caller passing a position.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function attributes(TaskRequest $request, array $extra = []): array
    {
        $validated = $request->safe()->only(['title', 'description', 'status', 'assignee_id', 'due_date', 'phase']);
        $validated['status'] = $validated['status'] ?? 'todo';

        if (array_key_exists('position', $extra)) {
            $extra['created_by'] = $request->user()->id;
        }

        return array_merge($validated, $extra);
    }
}
