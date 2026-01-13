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

    public function test_guests_cannot_store_a_post()
    {
        $response = $this->post('/posts', [
            'title' => 'New Post by Guest',
            'body' => 'This should not be saved.',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_store_post_validation_fails_for_missing_title()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/posts', [
            'body' => 'Body without a title',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }

    public function test_authenticated_user_can_store_a_post()
    {
        $user = User::factory()->create();
        $postData = [
            'title' => 'My First Awesome Post',
            'body' => 'This is the content of my very first post.',
        ];

        $response = $this->actingAs($user)->postJson('/posts', $postData);

        $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'My First Awesome Post']);

        $this->assertDatabaseHas('posts', [
            'title' => 'My First Awesome Post',
            'user_id' => $user->id,
            'slug' => 'my-first-awesome-post',
        ]);
    }

    public function test_show_returns_404_for_draft_post()
    {
        $user = User::factory()->create();
        $draftPost = Post::factory()->for($user, 'author')->draft()->create();

        $response = $this->getJson('/posts/'.$draftPost->id);

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_scheduled_post()
    {
        $user = User::factory()->create();
        $scheduledPost = Post::factory()->for($user, 'author')->scheduled()->create();

        $response = $this->getJson('/posts/'.$scheduledPost->id);

        $response->assertStatus(404);
    }

    public function test_show_returns_published_post()
    {
        $user = User::factory()->create();
        $publishedPost = Post::factory()->for($user, 'author')->published()->create();

        $response = $this->getJson('/posts/'.$publishedPost->id);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $publishedPost->id,
                    'title' => $publishedPost->title,
                    'author' => [
                        'id' => $user->id,
                    ],
                ],
            ]);
    }

    public function test_guests_cannot_access_edit_post_page()
    {
        // A user must be created to satisfy the user_id foreign key constraint
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create();

        $response = $this->get('/posts/'.$post->id.'/edit');

        $response->assertRedirect('/login');
    }

    public function test_users_cannot_edit_other_users_posts()
    {
        $postOwner = User::factory()->create();
        $post = Post::factory()->for($postOwner, 'author')->create();

        $anotherUser = User::factory()->create();

        $response = $this->actingAs($anotherUser)->get('/posts/'.$post->id.'/edit');

        $response->assertStatus(403);
    }

    public function test_post_author_can_access_edit_post_page()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->get('/posts/'.$post->id.'/edit');

        $response->assertStatus(200);
        $response->assertSee('posts.edit');
    }

    public function test_guests_cannot_update_a_post()
    {
        // A user must be created to satisfy the user_id foreign key constraint
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create();
        $response = $this->putJson('/posts/'.$post->id, ['title' => 'Updated Title', 'body' => 'Updated Body']);
        $response->assertUnauthorized(); // Expecting 401 as it's a JSON request
    }

    public function test_users_cannot_update_other_users_posts()
    {
        $postOwner = User::factory()->create();
        $post = Post::factory()->for($postOwner, 'author')->create();
        $anotherUser = User::factory()->create();

        $response = $this->actingAs($anotherUser)->putJson('/posts/'.$post->id, ['title' => 'Updated Title', 'body' => 'Updated Body']);

        $response->assertStatus(403);
    }

    public function test_update_post_validation_fails_for_missing_title()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->putJson('/posts/'.$post->id, ['body' => 'Body without a title']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }

    public function test_post_author_can_update_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create([
            'title' => 'Original Title',
            'body' => 'Original Body',
        ]);

        $updateData = ['title' => 'Updated Awesome Title', 'body' => 'Updated awesome body.'];

        $response = $this->actingAs($user)->putJson('/posts/'.$post->id, $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Updated Awesome Title']);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Awesome Title',
            'slug' => 'updated-awesome-title',
        ]);
    }

    public function test_guests_cannot_delete_a_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create();

        $response = $this->deleteJson('/posts/'.$post->id);

        $response->assertUnauthorized();
    }

    public function test_users_cannot_delete_other_users_posts()
    {
        $postOwner = User::factory()->create();
        $post = Post::factory()->for($postOwner, 'author')->create();
        $anotherUser = User::factory()->create();

        $response = $this->actingAs($anotherUser)->deleteJson('/posts/'.$post->id);

        $response->assertStatus(403);
    }

    public function test_post_author_can_delete_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create();

        $response = $this->actingAs($user)->deleteJson('/posts/'.$post->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
