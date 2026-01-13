<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $posts = Post::active()
            ->with('author:id,name,email')
            ->latest('published_at')
            ->paginate(20);

        return PostResource::collection($posts);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return 'posts.create';
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = new Post;
        $post->user_id = $request->user()->id;
        $post->title = $request->validated('title');
        $post->body = $request->validated('body');
        $post->slug = Str::slug($request->validated('title'));
        $post->is_draft = false;
        $post->published_at = now();
        $post->save();

        return PostResource::make($post->load('author'))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post): PostResource
    {
        if ($post->is_draft || $post->published_at->isFuture()) {
            abort(404);
        }

        return PostResource::make($post->load('author'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Post $post)
    {
        return 'posts.edit';
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $post->title = $request->validated('title');
        $post->body = $request->validated('body');
        $post->slug = Str::slug($request->validated('title'));
        $post->save();

        return PostResource::make($post->load('author'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        $post->delete();

        return response()->noContent();
    }
}
