<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\MoveTaskRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The kanban board.
 *
 * Every status column is present in the payload even when it holds no cards —
 * a client must be able to render an empty column as a drop target, and
 * inferring the column set from the cards that happen to exist would make
 * empty columns disappear.
 */
class BoardController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $tasks = Task::query()
            ->whereNull('parent_id') // top-level tasks are the cards
            ->with(['release.project', 'assignee', 'subtasks'])
            ->withCount('comments')
            ->when($this->filterId($request, 'release_id'), fn ($q, $id) => $q->where('release_id', $id))
            ->when($this->filterId($request, 'assignee_id'), fn ($q, $id) => $q->where('assignee_id', $id))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $columns = [];
        foreach (Task::STATUSES as $status => $label) {
            $cards = $tasks->where('status', $status)->values();

            $columns[] = [
                'status' => $status,
                'label' => $label,
                'color' => Task::STATUS_COLORS[$status] ?? 'gray',
                'count' => $cards->count(),
                'tasks' => TaskResource::collection($cards)->resolve($request),
            ];
        }

        return $this->ok([
            'columns' => $columns,
            'filters' => [
                'release_id' => $this->filterId($request, 'release_id'),
                'assignee_id' => $this->filterId($request, 'assignee_id'),
            ],
        ]);
    }

    /** Quick-add straight onto a column. */
    public function storeTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'release_id' => ['required', Rule::exists('releases', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $release = Release::findOrFail($data['release_id']);

        $task = $release->tasks()->create([
            'title' => $data['title'],
            'status' => $data['status'],
            'parent_id' => null,
            'created_by' => $request->user()->id,
            'position' => $release->rootTasks()->count(),
        ]);

        return $this->created(
            new TaskResource($task->load(['release.project', 'assignee'])->loadCount('comments')),
            'Task added to the board.'
        );
    }

    /**
     * A drag: set the card's status and renumber the target column in one
     * transaction, so the board can never be observed half-moved.
     */
    public function move(MoveTaskRequest $request, Task $task): JsonResponse
    {
        DB::transaction(function () use ($request, $task) {
            $task->update(['status' => $request->string('status')->toString()]);

            foreach ($request->input('ordered_ids', []) as $index => $id) {
                Task::whereKey($id)->whereNull('parent_id')->update(['position' => $index]);
            }
        });

        return $this->ok(
            new TaskResource($task->fresh()->load(['release.project', 'assignee'])->loadCount('comments')),
            'Card moved.'
        );
    }
}
