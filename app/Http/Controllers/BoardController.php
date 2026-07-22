<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function index(Request $request): View
    {
        $releaseId = $request->filled('release_id') ? (int) $request->integer('release_id') : null;
        $assigneeId = $request->filled('assignee_id') ? (int) $request->integer('assignee_id') : null;

        $tasks = Task::query()
            ->whereNull('parent_id') // top-level tasks are the cards
            ->with(['release.project', 'assignee', 'subtasks'])
            ->withCount('comments')
            ->when($releaseId, fn ($q) => $q->where('release_id', $releaseId))
            ->when($assigneeId, fn ($q) => $q->where('assignee_id', $assigneeId))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        // Group into the four status columns (every status present, even if empty).
        $columns = collect(array_keys(Task::STATUSES))
            ->mapWithKeys(fn ($status) => [$status => $tasks->where('status', $status)->values()])
            ->all();

        return view('board.index', [
            'columns' => $columns,
            'statuses' => Task::STATUSES,
            'release' => $releaseId ? Release::find($releaseId) : null,
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'filters' => ['release_id' => $releaseId, 'assignee_id' => $assigneeId],
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'release_id' => ['required', Rule::exists('releases', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
        ]);

        $release = Release::findOrFail($data['release_id']);
        $release->tasks()->create([
            'title' => $data['title'],
            'status' => $data['status'],
            'parent_id' => null,
            'created_by' => $request->user()->id,
            'position' => $release->rootTasks()->count(),
        ]);

        return back()->with('success', 'Task added to the board.');
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'ordered_ids' => ['array'],
            'ordered_ids.*' => ['integer'],
        ]);

        DB::transaction(function () use ($data, $task) {
            $task->update(['status' => $data['status']]);

            // Renumber positions for the cards now in the target column.
            foreach (($data['ordered_ids'] ?? []) as $index => $id) {
                Task::whereKey($id)->whereNull('parent_id')->update(['position' => $index]);
            }
        });

        return response()->json(['ok' => true]);
    }
}
