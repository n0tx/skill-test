<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the posts.index route.
     *
     * @return void
     */
    public function test_posts_index_returns_paginated_active_posts_with_author()
    {
        // 1. Arrange
        $user = User::factory()->create();

        // Create posts with different statuses
        $publishedPost = Post::factory()->for($user, 'author')->published()->create();
        Post::factory()->for($user, 'author')->draft()->create();
        Post::factory()->for($user, 'author')->scheduled()->create();

        // 2. Act
        $response = $this->getJson('/posts');

        // 3. Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $publishedPost->id)
            ->assertJsonPath('data.0.author.id', $user->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'body',
                        'published_at',
                        'author' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_guests_cannot_access_create_post_page()
    {
        $response = $this->get('/posts/create');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_access_create_post_page()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/posts/create');

        $response->assertStatus(200);
        $response->assertSee('posts.create');
    }
}
