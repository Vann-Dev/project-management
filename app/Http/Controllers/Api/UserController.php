<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'type', 'email_verified_at', 'created_at'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->string('search') . '%')
                    ->orWhere('email', 'like', '%' . $request->string('search') . '%');
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->only(['id', 'name', 'email', 'type', 'email_verified_at', 'created_at']));
    }
}
