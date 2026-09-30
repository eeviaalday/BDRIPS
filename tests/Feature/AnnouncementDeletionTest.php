<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a published announcement takes it off the public news feed
 * immediately and there is no undo, so the button is behind a confirmation
 * dialog rather than a single click. The route's own role middleware and the
 * controller remain the actual enforcement - this file only proves the UI asks
 * first, and that a secretary can still delete.
 */
class AnnouncementDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function secretary(): User
    {
        return User::factory()->secretary()->create();
    }

    public function test_the_delete_button_opens_a_confirmation_rather_than_deleting_on_click(): void
    {
        $announcement = Announcement::factory()->create(['title' => 'Barangay Assembly']);

        $content = $this->actingAs($this->secretary())
            ->get(route('staff.announcements.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Delete this announcement?', $content);
        $this->assertStringContainsString('Barangay Assembly', $content);
        $this->assertStringContainsString(route('staff.announcements.destroy', $announcement), $content);

        // The old markup posted straight from the row.
        $this->assertStringNotContainsString('onsubmit="return confirm', $content);
    }

    public function test_a_secretary_can_still_delete_the_announcement(): void
    {
        $announcement = Announcement::factory()->create();

        $this->actingAs($this->secretary())
            ->from(route('staff.announcements.index'))
            ->delete(route('staff.announcements.destroy', $announcement))
            ->assertRedirect(route('staff.announcements.index'));

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_a_resident_cannot_delete_an_announcement(): void
    {
        $announcement = Announcement::factory()->create();

        $this->actingAs(User::factory()->resident()->create())
            ->delete(route('staff.announcements.destroy', $announcement))
            ->assertForbidden();

        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
    }

    public function test_a_kagawad_cannot_delete_an_announcement(): void
    {
        $announcement = Announcement::factory()->create();

        $this->actingAs(User::factory()->kagawad()->create())
            ->delete(route('staff.announcements.destroy', $announcement))
            ->assertForbidden();

        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
    }
}
