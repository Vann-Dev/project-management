<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Models\Ticket;
use Illuminate\Http\Request;

trait ApiAccess
{
    private function authorizeProjectAccess(Request $request, Project $project): void
    {
        abort_unless(
            $project->owner_id === $request->user()->id
            || $project->users()->where('users.id', $request->user()->id)->exists(),
            403
        );
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
}
