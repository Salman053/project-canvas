<?php

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Workbench\App\Models\Post;
use Workbench\App\Jobs\ProcessPost;
use Workbench\App\Events\PostCreated;

class PostController extends Controller
{
    public function __construct(
        private readonly Post $posts,
    ) {
        $this->middleware('auth');
    }

    public function index(): JsonResponse
    {
        return response()->json($this->posts->all());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->posts->with('comments')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $post = $this->posts->create($request->only(['title', 'body', 'user_id']));

        ProcessPost::dispatch($post);

        event(new PostCreated($post));

        return response()->json($post, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $post = $this->posts->findOrFail($id);
        $post->update($request->only(['title', 'body']));

        return response()->json($post);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->posts->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
