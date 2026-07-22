<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyNotesTest extends TestCase
{
    use RefreshDatabase;

    private function note(User $user, array $attrs = []): Note
    {
        return $user->notes()->create(array_merge([
            'date' => '2026-07-22', 'body' => 'A note', 'visibility' => 'private',
        ], $attrs));
    }

    public function test_user_can_add_private_and_shared_notes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), [
            'date' => '2026-07-22', 'body' => 'Private one', 'visibility' => 'private',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('notes.store'), [
            'date' => '2026-07-22', 'body' => 'Shared one', 'visibility' => 'shared',
        ])->assertRedirect();

        $this->assertSame(2, Note::count());
        $this->assertSame($user->id, Note::first()->user_id);
    }

    public function test_empty_or_invalid_visibility_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('notes.store'), ['date' => '2026-07-22', 'body' => '', 'visibility' => 'nope'])
            ->assertSessionHasErrors(['body', 'visibility']);
    }

    public function test_day_view_shows_own_and_shared_but_hides_others_private(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->note($me, ['body' => 'My private', 'visibility' => 'private']);
        $this->note($other, ['body' => 'Their shared', 'visibility' => 'shared']);
        $this->note($other, ['body' => 'Their secret', 'visibility' => 'private']);

        $this->actingAs($me)->get(route('notes.index', ['date' => '2026-07-22']))
            ->assertOk()
            ->assertSee('My private')
            ->assertSee('Their shared')
            ->assertDontSee('Their secret');
    }

    public function test_only_author_can_edit_or_delete(): void
    {
        $author = User::factory()->create();
        $note = $this->note($author, ['visibility' => 'shared']);
        $other = User::factory()->create();

        $this->actingAs($other)->put(route('notes.update', $note), [
            'date' => '2026-07-22', 'body' => 'hijack', 'visibility' => 'shared',
        ])->assertForbidden();

        $this->actingAs($other)->delete(route('notes.destroy', $note))->assertForbidden();

        $this->actingAs($author)->put(route('notes.update', $note), [
            'date' => '2026-07-22', 'body' => 'edited', 'visibility' => 'private',
        ])->assertRedirect();
        $this->assertSame('edited', $note->refresh()->body);
        $this->assertSame('private', $note->visibility);

        $this->actingAs($author)->delete(route('notes.destroy', $note))->assertRedirect();
        $this->assertSame(0, Note::count());
    }

    public function test_notes_require_auth(): void
    {
        $this->get(route('notes.index'))->assertRedirect(route('login'));
    }

    public function test_rich_text_is_sanitized(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), [
            'date' => '2026-07-22',
            'body' => '<strong>Hi</strong><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
            'visibility' => 'shared',
        ])->assertRedirect();

        $body = Note::first()->body;
        $this->assertStringContainsString('<strong>Hi</strong>', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringNotContainsString('javascript:', $body);
    }

    public function test_visually_empty_html_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('notes.store'), ['date' => '2026-07-22', 'body' => '<div><br></div>', 'visibility' => 'shared'])
            ->assertSessionHasErrors('body');
    }
}
