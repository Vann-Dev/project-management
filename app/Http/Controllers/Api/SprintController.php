<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    use ApiAccess;

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        return response()->json(
            $project->sprints()
                ->with(['epic:id,name,project_id'])
                ->orderBy('starts_at')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ]);

        $data['project_id'] = $project->id;
        $sprint = Sprint::create($data)->load(['epic:id,name,project_id']);

        return response()->json($sprint, 201);
    }

    public function show(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->authorizeSprintAccess($request, $project, $sprint);

        return response()->json($sprint->load(['epic:id,name,project_id', 'tickets:id,name,code,sprint_id,status_id']));
    }

    public function update(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->authorizeSprintAccess($request, $project, $sprint);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after_or_equal:starts_at'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
        ]);

        $sprint->update($data);

        return response()->json($sprint->load(['epic:id,name,project_id']));
    }

    public function destroy(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->authorizeSprintAccess($request, $project, $sprint);

        $sprint->delete();

        return response()->json(null, 204);
    }

    private function authorizeSprintAccess(Request $request, Project $project, Sprint $sprint): void
    {
        abort_unless($sprint->project_id === $project->id, 404);
        $this->authorizeProjectAccess($request, $project);
    }
}
