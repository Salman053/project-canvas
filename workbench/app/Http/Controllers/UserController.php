<?php

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Workbench\App\Models\User;

class UserController extends Controller
{
    public function __construct(
        private readonly User $users,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->users->all());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->users->with('posts')->findOrFail($id));
    }
}
