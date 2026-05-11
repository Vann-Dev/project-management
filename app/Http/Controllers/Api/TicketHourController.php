<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketHourController extends Controller
{
    use ApiAccess;

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        return response()->json(
            $ticket->hours()
                ->with(['user:id,name,email', 'activity:id,name'])
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:0.1'],
            'comment' => ['nullable', 'string'],
            'activity_id' => ['nullable', 'exists:activities,id'],
        ]);

        $hour = TicketHour::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'value' => $data['value'],
            'comment' => $data['comment'] ?? null,
            'activity_id' => $data['activity_id'] ?? null,
        ])->load(['user:id,name,email', 'activity:id,name']);

        return response()->json($hour, 201);
    }

    public function show(Request $request, Ticket $ticket, TicketHour $hour): JsonResponse
    {
        $this->authorizeHourAccess($request, $ticket, $hour);

        return response()->json($hour->load(['user:id,name,email', 'activity:id,name']));
    }

    public function update(Request $request, Ticket $ticket, TicketHour $hour): JsonResponse
    {
        $this->authorizeHourAccess($request, $ticket, $hour);
        abort_unless($hour->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'value' => ['sometimes', 'required', 'numeric', 'min:0.1'],
            'comment' => ['nullable', 'string'],
            'activity_id' => ['nullable', 'exists:activities,id'],
        ]);

        $hour->update($data);

        return response()->json($hour->load(['user:id,name,email', 'activity:id,name']));
    }

    public function destroy(Request $request, Ticket $ticket, TicketHour $hour): JsonResponse
    {
        $this->authorizeHourAccess($request, $ticket, $hour);
        abort_unless($hour->user_id === $request->user()->id, 403);

        $hour->delete();

        return response()->json(null, 204);
    }

    private function authorizeHourAccess(Request $request, Ticket $ticket, TicketHour $hour): void
    {
        abort_unless($hour->ticket_id === $ticket->id, 404);
        $this->authorizeTicketAccess($request, $ticket);
    }
}
