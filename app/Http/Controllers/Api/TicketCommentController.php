<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    use ApiAccess;

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        return response()->json(
            $ticket->comments()
                ->with(['user:id,name,email'])
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $data = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ])->load(['user:id,name,email']);

        return response()->json($comment, 201);
    }

    public function show(Request $request, Ticket $ticket, TicketComment $comment): JsonResponse
    {
        $this->authorizeCommentAccess($request, $ticket, $comment);

        return response()->json($comment->load(['user:id,name,email']));
    }

    public function update(Request $request, Ticket $ticket, TicketComment $comment): JsonResponse
    {
        $this->authorizeCommentAccess($request, $ticket, $comment);
        abort_unless($comment->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $comment->update($data);

        return response()->json($comment->load(['user:id,name,email']));
    }

    public function destroy(Request $request, Ticket $ticket, TicketComment $comment): JsonResponse
    {
        $this->authorizeCommentAccess($request, $ticket, $comment);
        abort_unless($comment->user_id === $request->user()->id, 403);

        $comment->delete();

        return response()->json(null, 204);
    }

    private function authorizeCommentAccess(Request $request, Ticket $ticket, TicketComment $comment): void
    {
        abort_unless($comment->ticket_id === $ticket->id, 404);
        $this->authorizeTicketAccess($request, $ticket);
    }
}
