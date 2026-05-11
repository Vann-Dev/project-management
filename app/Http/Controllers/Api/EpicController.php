<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Epic;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EpicController extends Controller
{
    use ApiAccess;

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        return response()->json(
            $project->epics()
                ->with(['parent:id,name,project_id'])
                ->orderBy('starts_at')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'parent_id' => ['nullable', 'exists:epics,id'],
        ]);

        $data['project_id'] = $project->id;
        $epic = Epic::create($data)->load(['parent:id,name,project_id']);

        return response()->json($epic, 201);
    }

    public function show(Request $request, Project $project, Epic $epic): JsonResponse
    {
        $this->authorizeEpicAccess($request, $project, $epic);

        return response()->json($epic->load(['parent:id,name,project_id', 'tickets:id,name,code,epic_id,status_id']));
    }

    public function update(Request $request, Project $project, Epic $epic): JsonResponse
    {
        $this->authorizeEpicAccess($request, $project, $epic);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after_or_equal:starts_at'],
            'parent_id' => ['nullable', 'exists:epics,id'],
        ]);

        $epic->update($data);

        return response()->json($epic->load(['parent:id,name,project_id']));
    }

    public function destroy(Request $request, Project $project, Epic $epic): JsonResponse
    {
        $this->authorizeEpicAccess($request, $project, $epic);

        $epic->delete();

        return response()->json(null, 204);
    }

    private function authorizeEpicAccess(Request $request, Project $project, Epic $epic): void
    {
        abort_unless($epic->project_id === $project->id, 404);
        $this->authorizeProjectAccess($request, $project);
    }
}
