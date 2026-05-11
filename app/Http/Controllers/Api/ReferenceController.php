<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ProjectStatus;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function projectStatuses(): JsonResponse
    {
        return response()->json(ProjectStatus::query()->orderBy('name')->get());
    }

    public function ticketStatuses(Request $request): JsonResponse
    {
        $statuses = TicketStatus::query()
            ->when($request->filled('project_id'), fn($query) => $query->where('project_id', $request->integer('project_id')))
            ->when(!$request->filled('project_id'), fn($query) => $query->whereNull('project_id'))
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json($statuses);
    }

    public function ticketTypes(): JsonResponse
    {
        return response()->json(TicketType::query()->orderBy('name')->get());
    }

    public function ticketPriorities(): JsonResponse
    {
        return response()->json(TicketPriority::query()->orderBy('name')->get());
    }

    public function activities(): JsonResponse
    {
        return response()->json(Activity::query()->orderBy('name')->get());
    }
}
