<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->with([
                'project:id,name,ticket_prefix,owner_id',
                'owner:id,name,email',
                'responsible:id,name,email',
                'status:id,name,color',
                'type:id,name,icon,color',
                'priority:id,name,color',
            ])
            ->where(function ($query) use ($request) {
                $query->where('owner_id', $request->user()->id)
                    ->orWhere('responsible_id', $request->user()->id)
                    ->orWhereHas('project', function ($query) use ($request) {
                        $query->where('owner_id', $request->user()->id)
                            ->orWhereHas('users', fn($query) => $query->where('users.id', $request->user()->id));
                    });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status_id' => ['nullable', 'exists:ticket_statuses,id'],
            'type_id' => ['nullable', 'exists:ticket_types,id'],
            'priority_id' => ['nullable', 'exists:ticket_priorities,id'],
            'estimation' => ['nullable', 'numeric', 'min:0'],
            'epic_id' => ['nullable', 'exists:epics,id'],
            'sprint_id' => ['nullable', 'exists:sprints,id'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        $this->authorizeProjectAccess($request, $project);

        $data['owner_id'] = $request->user()->id;
        $data['status_id'] ??= TicketStatus::where('project_id', $project->status_type === 'custom' ? $project->id : null)
            ->where('is_default', true)
            ->value('id');
        $data['type_id'] ??= TicketType::where('is_default', true)->value('id');
        $data['priority_id'] ??= TicketPriority::where('is_default', true)->value('id');

        $ticket = Ticket::create($data)->load($this->relationships());

        return response()->json($ticket, 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        return response()->json($ticket->load($this->relationships()));
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'status_id' => ['sometimes', 'required', 'exists:ticket_statuses,id'],
            'type_id' => ['sometimes', 'required', 'exists:ticket_types,id'],
            'priority_id' => ['sometimes', 'required', 'exists:ticket_priorities,id'],
            'estimation' => ['nullable', 'numeric', 'min:0'],
            'epic_id' => ['nullable', 'exists:epics,id'],
            'sprint_id' => ['nullable', 'exists:sprints,id'],
        ]);

        $ticket->update($data);

        return response()->json($ticket->load($this->relationships()));
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $ticket->delete();

        return response()->json(null, 204);
    }

    private function relationships(): array
    {
        return [
            'project:id,name,ticket_prefix,owner_id',
            'owner:id,name,email',
            'responsible:id,name,email',
            'status:id,name,color',
            'type:id,name,icon,color',
            'priority:id,name,color',
            'epic:id,name,project_id',
            'sprint:id,name,project_id',
        ];
    }

    private function authorizeTicketAccess(Request $request, Ticket $ticket): void
    {
        abort_unless(
            $ticket->owner_id === $request->user()->id
            || $ticket->responsible_id === $request->user()->id
            || $ticket->project->owner_id === $request->user()->id
            || $ticket->project->users()->where('users.id', $request->user()->id)->exists(),
            403
        );
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
