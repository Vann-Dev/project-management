<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EpicController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\SprintController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketCommentController;
use App\Http\Controllers\Api\TicketHourController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('references/project-statuses', [ReferenceController::class, 'projectStatuses']);
    Route::get('references/ticket-statuses', [ReferenceController::class, 'ticketStatuses']);
    Route::get('references/ticket-types', [ReferenceController::class, 'ticketTypes']);
    Route::get('references/ticket-priorities', [ReferenceController::class, 'ticketPriorities']);
    Route::get('references/activities', [ReferenceController::class, 'activities']);

    Route::apiResource('users', UserController::class)->only(['index', 'show']);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('projects.epics', EpicController::class);
    Route::apiResource('projects.sprints', SprintController::class);
    Route::apiResource('tickets', TicketController::class);
    Route::apiResource('tickets.comments', TicketCommentController::class);
    Route::apiResource('tickets.hours', TicketHourController::class);
});
