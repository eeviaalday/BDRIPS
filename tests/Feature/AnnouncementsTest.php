<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Announcement management, and the boundary it has to hold.
 *
 * The public page is the only place a notice is read by a resident, and it
 * filters on published_at. So most of what matters here is not "can staff write
 * a notice" but "does a draft stay a draft": a future-dated notice leaking into
 * the public page is the failure that matters, and it is easy to cause with a
 * date field.
 */
class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The public boundary
    // ---------------------------------------------------------------------

    #[Test]
    public function a_draft_never_reaches_the_public_page(): void
    {
        $this->published();
        Announcement::factory()->create(['title' => 'Secret draft']);

        $content = $this->get(route('announcements.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Secret draft', $content);
    }

    #[Test]
    public function a_scheduled_notice_does_not_appear_before_its_date(): void
    {
        $this->published();
        Announcement::factory()->create([
            'title' => 'Future notice',
            'published_at' => now()->addWeek(),
        ]);

        $content = $this->get(route('announcements.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Future notice', $content);
    }

    /**
     * The scheduled notice appearing on the day it is due is the whole point of
     * scheduling, so it is checked from both sides of the boundary rather than
     * only the safe side.
     */
    #[Test]
    public function a_scheduled_notice_appears_once_its_date_has_passed(): void
    {
        $this->published();
        Announcement::factory()->create([
            'title' => 'Notice from last week',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Notice from last week');
    }

    // ---------------------------------------------------------------------
    // Writing
    // ---------------------------------------------------------------------

    #[Test]
    public function a_new_announcement_is_a_draft_by_default(): void
    {
        $this->actingAs($this->staff())
            ->post(route('staff.announcements.store'), [
                'title' => 'Barangay Meeting',
                'body' => 'The meeting is on Friday at the covered court.',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $announcement = Announcement::query()->latest('id')->firstOrFail();

        $this->assertNull($announcement->published_at);
        $this->assertFalse($announcement->isPublished());
    }

    #[Test]
    public function an_announcement_needs_a_title_and_a_body(): void
    {
        $this->actingAs($this->staff())
            ->post(route('staff.announcements.store'), [])
            ->assertSessionHasErrors(['title', 'body']);

        $this->assertSame(0, Announcement::query()->count());
    }

    #[Test]
    public function publishing_makes_a_notice_public(): void
    {
        $announcement = Announcement::factory()->create(['published_at' => null]);

        $this->actingAs($this->staff())
            ->post(route('staff.announcements.publish', $announcement))
            ->assertRedirect();

        $this->assertTrue($announcement->fresh()->isPublished());

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee($announcement->title);
    }

    #[Test]
    public function unpublishing_withdraws_a_notice_from_the_public_page(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => 'Withdraw me',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('announcements.index'))->assertSee('Withdraw me');

        $this->actingAs($this->staff())
            ->post(route('staff.announcements.publish', $announcement))
            ->assertRedirect();

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Withdraw me');
    }

    /**
     * Toggling publish has to be a real toggle, not "set published_at to now".
     * If the second click re-published, an operator could never take a notice
     * back down.
     */
    #[Test]
    public function publishing_twice_actually_unpublishes(): void
    {
        $announcement = Announcement::factory()->create(['published_at' => now()->subDay()]);

        $this->actingAs($this->staff())->post(route('staff.announcements.publish', $announcement));
        $this->assertNull($announcement->fresh()->published_at, 'The second click must clear the date, not re-set it.');

        $this->actingAs($this->staff())->post(route('staff.announcements.publish', $announcement));
        $this->assertTrue($announcement->fresh()->isPublished());
    }

    #[Test]
    public function editing_a_notice_keeps_its_publication_date(): void
    {
        // A whole second, because the column is a timestamp and MySQL drops
        // microseconds - otherwise this compares precision, not behaviour.
        $published = now()->subDays(3)->startOfSecond();
        $announcement = Announcement::factory()->create(['published_at' => $published]);

        $this->actingAs($this->staff())
            // The date field is always in the form, so an untouched one posts an
            // empty string. Posting it empty is what reproduces the bug.
            ->put(route('staff.announcements.update', $announcement), [
                'title' => 'Corrected title',
                'body' => 'Corrected body text.',
                'published_at' => '',
            ])
            ->assertRedirect();

        $announcement->refresh();

        $this->assertSame('Corrected title', $announcement->title);
        $this->assertNotNull(
            $announcement->published_at,
            'Editing a live notice must not silently unpublish it.'
        );
        $this->assertTrue(
            $announcement->published_at->equalTo($published),
            'Editing a live notice must not move its publication date either.'
        );
    }

    #[Test]
    public function deleting_a_notice_removes_it_from_the_public_page(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => 'Delete me',
            'published_at' => now()->subDay(),
        ]);

        $this->actingAs($this->staff())
            ->delete(route('staff.announcements.destroy', $announcement))
            ->assertRedirect();

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Delete me');
    }

    // ---------------------------------------------------------------------
    // Pinning
    // ---------------------------------------------------------------------

    #[Test]
    public function pinning_also_schedules_the_notice(): void
    {
        $announcement = Announcement::factory()->create(['published_at' => null]);
        $when = now()->addDay()->startOfMinute();

        $this->actingAs($this->staff())
            ->post(route('staff.announcements.pin', $announcement), [
                'published_at' => $when->toDateTimeString(),
            ])
            ->assertRedirect();

        $announcement->refresh();

        $this->assertTrue($announcement->isPinned());
        $this->assertTrue($announcement->published_at->equalTo($when));
    }

    /**
     * A pin needs a publication date, because the public page only reads pinned
     * notices that are also live. Pinning a draft would make it visible on the
     * management screen and invisible to the public, which is the confusing
     * half of the two systems disagreeing.
     */
    #[Test]
    public function pinning_without_a_date_is_refused(): void
    {
        $announcement = Announcement::factory()->create(['published_at' => null]);

        $this->actingAs($this->staff())
            ->post(route('staff.announcements.pin', $announcement))
            ->assertSessionHasErrors('published_at');

        $this->assertFalse($announcement->fresh()->isPinned());
    }

    #[Test]
    public function a_pin_date_in_the_past_is_refused(): void
    {
        $announcement = Announcement::factory()->create(['published_at' => null]);

        $this->actingAs($this->staff())
            ->post(route('staff.announcements.pin', $announcement), [
                'published_at' => now()->subDay()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('published_at');
    }

    #[Test]
    public function unpinning_leaves_the_notice_published(): void
    {
        $announcement = Announcement::factory()->create([
            'published_at' => now()->subDay(),
            'pinned_at' => now()->subDay(),
        ]);

        $this->actingAs($this->staff())
            ->post(route('staff.announcements.unpin', $announcement))
            ->assertRedirect();

        $announcement->refresh();

        $this->assertFalse($announcement->isPinned());
        $this->assertTrue(
            $announcement->isPublished(),
            'Unpinning lifts it from the top; it does not withdraw it.'
        );
    }

    // ---------------------------------------------------------------------
    // The management screen
    // ---------------------------------------------------------------------

    #[Test]
    public function the_filter_does_not_widen_the_state(): void
    {
        $this->published();
        Announcement::factory()->create(['title' => 'Zeta hidden', 'published_at' => null]);

        $content = $this->actingAs($this->staff())
            ->get(route('staff.announcements.index', ['state' => 'drafts', 'q' => 'Zeta']))
            ->assertOk()
            ->getContent();

        // The draft matches both filters, so it must appear.
        $this->assertStringContainsString('Zeta hidden', $content);

        $published = Announcement::factory()->create(['title' => 'Alpha published', 'published_at' => now()->subDay()]);
        Announcement::factory()->create(['title' => 'Alpha draft', 'published_at' => null]);

        $content = $this->actingAs($this->staff())
            ->get(route('staff.announcements.index', ['state' => 'drafts', 'q' => 'Alpha']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Alpha draft', $content);
        $this->assertStringNotContainsString(
            $published->title,
            $content,
            'The search term matched a notice outside the state filter.'
        );
    }

    #[Test]
    public function every_write_is_audited(): void
    {
        $staff = $this->staff();
        $announcement = Announcement::factory()->create([
            'published_at' => now()->subDay(),
            'pinned_at' => now()->subDay(),
        ]);

        $this->actingAs($staff)
            ->post(route('staff.announcements.store'), ['title' => 'Audited', 'body' => 'Body text.'])
            ->assertRedirect();

        $live = Announcement::query()->latest('id')->firstOrFail();

        $this->actingAs($staff)->put(route('staff.announcements.update', $live), [
            'title' => 'Audited v2',
            'body' => 'Body text.',
        ]);
        $this->actingAs($staff)->post(route('staff.announcements.publish', $live));
        $this->actingAs($staff)->post(route('staff.announcements.unpin', $announcement));
        $this->actingAs($staff)->delete(route('staff.announcements.destroy', $announcement));

        foreach ([
            'announcement.created',
            'announcement.updated',
            'announcement.published',
            'announcement.unpinned',
            'announcement.deleted',
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $staff->id,
                'action' => $action,
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Who may write
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('nonStaffProvider')]
    public function a_non_staff_member_cannot_write_an_announcement(string $role): void
    {
        $announcement = Announcement::factory()->create(['title' => 'Untouched', 'published_at' => null]);
        $user = $this->userFor($role);

        $this->actingAs($user)->post(route('staff.announcements.store'), [
            'title' => 'Sneaky',
            'body' => 'Body text.',
        ])->assertForbidden();

        $this->actingAs($user)->put(route('staff.announcements.update', $announcement), [
            'title' => 'Overwritten',
            'body' => 'Body text.',
        ])->assertForbidden();

        $this->actingAs($user)->post(route('staff.announcements.publish', $announcement))->assertForbidden();
        $this->actingAs($user)->delete(route('staff.announcements.destroy', $announcement))->assertForbidden();

        $announcement->refresh();
        $this->assertSame('Untouched', $announcement->title);
        $this->assertNull($announcement->published_at);
    }

    #[Test]
    public function a_guest_cannot_manage_announcements(): void
    {
        $announcement = Announcement::factory()->create();

        $this->get(route('staff.announcements.index'))->assertRedirect(route('login'));
        $this->post(route('staff.announcements.publish', $announcement))->assertRedirect(route('login'));
        $this->delete(route('staff.announcements.destroy', $announcement))->assertRedirect(route('login'));

        $this->assertNull($announcement->fresh()->published_at);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonStaffProvider(): array
    {
        return ['resident' => ['resident'], 'kagawad' => ['kagawad']];
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function staff(): User
    {
        return User::factory()->secretary()->create(['is_verified' => true]);
    }

    private function userFor(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_verified' => true]);
    }

    /**
     * Something already public, so the public page is never empty and an
     * assertion about a hidden notice is not trivially true.
     */
    private function published(): Announcement
    {
        return Announcement::factory()->create([
            'title' => 'Already public',
            'published_at' => now()->subDay(),
        ]);
    }
}
