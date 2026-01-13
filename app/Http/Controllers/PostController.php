<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
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
    public function store(StorePostRequest $request): \Illuminate\Http\JsonResponse
    {
        $post = new Post;
        $post->user_id = $request->user()->id;
        $post->title = $request->validated('title');
        $post->body = $request->validated('body');
        $post->slug = Str::slug($request->validated('title'));
        // For now, let's assume new posts are published immediately.
        // We can adjust logic for drafts/scheduled later if needed.
        $post->is_draft = false;
        $post->published_at = now();
        $post->save();

        return PostResource::make($post->load('author'))->response()->setStatusCode(201);
    }
}
