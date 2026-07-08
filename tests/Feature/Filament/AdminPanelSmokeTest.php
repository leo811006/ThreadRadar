<?php

use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Models\Keyword;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

function actingAsAdmin(): User
{
    $user = User::factory()->create();
    test()->actingAs($user);

    return $user;
}

it('renders the admin login page', function () {
    $this->get('/admin/login')->assertSuccessful();
});

it('redirects unauthenticated users away from the dashboard', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('renders the dashboard for an authenticated user', function () {
    actingAsAdmin();

    $this->get('/admin')->assertSuccessful();
});

it('renders the keyword resource list page', function () {
    actingAsAdmin();
    Keyword::factory()->count(3)->create();

    $this->get('/admin/keywords')->assertSuccessful();
});

it('renders the keyword resource create page', function () {
    actingAsAdmin();

    $this->get('/admin/keywords/create')->assertSuccessful();
});

it('renders the keyword resource edit page', function () {
    actingAsAdmin();
    $keyword = Keyword::factory()->create();

    $this->get("/admin/keywords/{$keyword->id}/edit")->assertSuccessful();
});

it('renders the post resource list page', function () {
    actingAsAdmin();
    Post::factory()->count(3)->create();

    $this->get('/admin/posts')->assertSuccessful();
});

it('renders the post resource view page', function () {
    actingAsAdmin();
    $post = Post::factory()->create();

    $this->get("/admin/posts/{$post->id}")->assertSuccessful();
});

it('does not expose create/edit routes for posts since they are read-only', function () {
    actingAsAdmin();

    $this->get('/admin/posts/create')->assertNotFound();
});

it('does not render a create button on the post list page', function () {
    actingAsAdmin();

    Livewire::test(ListPosts::class)
        ->assertActionDoesNotExist('create');
});

it('does not render an edit button on the post view page', function () {
    actingAsAdmin();
    $post = Post::factory()->create();

    Livewire::test(ViewPost::class, ['record' => $post->getRouteKey()])
        ->assertActionDoesNotExist('edit');
});
