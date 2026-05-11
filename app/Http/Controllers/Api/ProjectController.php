<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->with(['owner:id,name,email', 'status:id,name,color'])
            ->where(function ($query) use ($request) {
                $query->where('owner_id', $request->user()->id)
                    ->orWhereHas('users', fn($query) => $query->where('users.id', $request->user()->id));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status_id' => ['nullable', 'exists:project_statuses,id'],
            'ticket_prefix' => ['required', 'string', 'max:3', 'unique:projects,ticket_prefix'],
            'status_type' => ['nullable', Rule::in(['default', 'custom'])],
            'type' => ['nullable', Rule::in(['kanban', 'scrum'])],
        ]);

        $data['owner_id'] = $request->user()->id;
        $data['status_id'] ??= ProjectStatus::where('is_default', true)->value('id');
        $data['status_type'] ??= 'default';
        $data['type'] ??= 'kanban';

        $project = Project::create($data)->load(['owner:id,name,email', 'status:id,name,color']);

        return response()->json($project, 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        return response()->json(
            $project->load([
                'owner:id,name,email',
                'status:id,name,color',
                'users:id,name,email',
                'tickets:id,name,code,status_id,project_id,owner_id,responsible_id',
            ])
        );
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status_id' => ['sometimes', 'required', 'exists:project_statuses,id'],
            'ticket_prefix' => [
                'sometimes',
                'required',
                'string',
                'max:3',
                Rule::unique('projects', 'ticket_prefix')->ignore($project->id),
            ],
            'status_type' => ['sometimes', 'required', Rule::in(['default', 'custom'])],
            'type' => ['sometimes', 'required', Rule::in(['kanban', 'scrum'])],
        ]);

        $project->update($data);

        return response()->json($project->load(['owner:id,name,email', 'status:id,name,color']));
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        $project->delete();

        return response()->json(null, 204);
    }

    private function authorizeProjectAccess(Request $request, Project $project): void
    {
        abort_unless(
            $project->owner_id === $request->user()->id
            || $project->users()->where('users.id', $request->user()->id)->exists(),
            403
        );
    }
}
