<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ProjectRequest;
use App\Http\Resources\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Projects. Reads are open to full-access roles; writes require the leadership
 * tier, enforced by the route middleware and by ProjectRequest::authorize().
 */
class ProjectController extends ApiController
{
    /** Active projects first, then archived — matching the web ordering. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Project::withCount('releases')
            ->when($request->filled('status'), fn ($q) => $request->input('status') === 'archived'
                ? $q->whereNotNull('archived_at')
                : $q->whereNull('archived_at'))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderByRaw('archived_at is not null')
            ->orderBy('name');

        return $this->paginate($request, $query, ProjectResource::class);
    }

    public function show(Project $project): JsonResponse
    {
        return $this->ok(new ProjectResource($project->loadCount('releases')));
    }

    public function store(ProjectRequest $request): JsonResponse
    {
        $project = Project::create($request->validated());

        return $this->created(
            new ProjectResource($project->loadCount('releases')),
            "Project “{$project->name}” created."
        );
    }

    public function update(ProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->validated());

        return $this->ok(
            new ProjectResource($project->loadCount('releases')),
            "Project “{$project->name}” updated."
        );
    }

    /**
     * A project that owns releases is never hard-deleted — deleting it would
     * cascade away release history. It is archived instead, exactly as the web
     * app refuses and directs.
     */
    public function destroy(Project $project): JsonResponse
    {
        abort_if(
            $project->releases()->exists(),
            422,
            'This project has releases and cannot be deleted. Archive it instead.'
        );

        $name = $project->name;
        $project->delete();

        return $this->message("Project “{$name}” deleted.");
    }

    public function archive(Project $project): JsonResponse
    {
        $project->update(['archived_at' => now()]);

        return $this->ok(new ProjectResource($project), "Project “{$project->name}” archived.");
    }

    public function restore(Project $project): JsonResponse
    {
        $project->update(['archived_at' => null]);

        return $this->ok(new ProjectResource($project), "Project “{$project->name}” restored.");
    }
}
