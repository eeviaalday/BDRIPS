<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffAnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    public static function staffProvider(): array
    {
        return [
            'admin' => ['admin'],
            'secretary' => ['secretary'],
        ];
    }

    #[DataProvider('staffProvider')]
    public function test_staff_can_open_the_announcement_index(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('staff.announcements.index'))
            ->assertOk();
    }

    #[DataProvider('staffProvider')]
    public function test_staff_can_open_the_announcement_create_form(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('staff.announcements.create'))
            ->assertOk();
    }

    #[DataProvider('staffProvider')]
    public function test_staff_can_create_an_announcement_and_is_redirected_to_the_staff_index(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)
            ->post(route('staff.announcements.store'), [
                'title' => 'Community Health Drive',
                'body' => 'A free health screening will be held at the Barangay Hall.',
            ])
            ->assertRedirect(route('staff.announcements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('announcements', [
            'title' => 'Community Health Drive',
            'created_by_user_id' => $user->id,
        ]);
    }

    #[DataProvider('staffProvider')]
    public function test_staff_can_create_a_published_announcement(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($user)
            ->post(route('staff.announcements.store'), [
                'title' => 'Published Immediately',
                'body' => 'Body copy for the public.',
                'publish_now' => '1',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $this->assertNotNull(Announcement::firstOrFail()->published_at);
    }

    public function test_announcement_creation_validates_input(): void
    {
        $this->actingAs(User::factory()->secretary()->create())
            ->post(route('staff.announcements.store'), [
                'title' => '',
                'body' => '',
            ])
            ->assertSessionHasErrors(['title', 'body']);

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_an_announcement_saved_without_a_category_falls_back_to_the_general_one(): void
    {
        $this->actingAs(User::factory()->secretary()->create())
            ->post(route('staff.announcements.store'), [
                'title' => 'No Category Given',
                'body' => 'The staff member left the category alone.',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $this->assertSame(Announcement::CATEGORY_GENERAL, Announcement::firstOrFail()->category);
    }

    public function test_a_category_outside_the_configured_list_is_rejected(): void
    {
        $this->actingAs(User::factory()->secretary()->create())
            ->post(route('staff.announcements.store'), [
                'title' => 'Unknown Category',
                'body' => 'A category that is not configured.',
                'category' => 'sports',
            ])
            ->assertSessionHasErrors('category');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_a_notice_is_saved_unpinned_and_without_a_location_by_default(): void
    {
        $this->actingAs(User::factory()->secretary()->create())
            ->post(route('staff.announcements.store'), [
                'title' => 'Ordinary Notice',
                'body' => 'Saved without touching the pin or the location.',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $announcement = Announcement::firstOrFail();

        $this->assertNull($announcement->pinned_at, 'A notice is pinned without being asked to be.');
        $this->assertFalse($announcement->isPinned());
        $this->assertNull($announcement->location);
    }

    public function test_staff_can_pin_a_notice_and_give_it_a_location(): void
    {
        $this->actingAs(User::factory()->secretary()->create())
            ->post(route('staff.announcements.store'), [
                'title' => 'Clean-Up Drive',
                'body' => 'The barangay clean-up drive is on Saturday.',
                'category' => 'community',
                'location' => 'Barangay Plaza',
                'publish_now' => '1',
                'pinned' => '1',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $announcement = Announcement::firstOrFail();

        $this->assertNotNull($announcement->pinned_at);
        $this->assertTrue($announcement->isPinned());
        $this->assertSame('Barangay Plaza', $announcement->location);
    }

    public function test_editing_a_pinned_notice_keeps_it_pinned(): void
    {
        $announcement = Announcement::factory()->publishedAndPinned()->create();

        $this->actingAs(User::factory()->secretary()->create())
            ->put(route('staff.announcements.update', $announcement), [
                'title' => 'Updated Title',
                'body' => 'Updated body copy.',
                'publish_now' => '1',
                'pinned' => '1',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $this->assertTrue($announcement->fresh()->isPinned());
    }

    public function test_unticking_the_pin_unpins_a_notice(): void
    {
        $announcement = Announcement::factory()->publishedAndPinned()->create();

        $this->actingAs(User::factory()->secretary()->create())
            ->put(route('staff.announcements.update', $announcement), [
                'title' => 'No Longer Pinned',
                'body' => 'The staff member unticked the pin.',
                'publish_now' => '1',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $this->assertNull($announcement->fresh()->pinned_at);
    }

    public function test_clearing_the_location_field_on_an_edit_removes_it(): void
    {
        $announcement = Announcement::factory()->published()->create(['location' => 'Old Place']);

        $this->actingAs(User::factory()->secretary()->create())
            ->put(route('staff.announcements.update', $announcement), [
                'title' => 'Relocated Notice',
                'body' => 'The place it happens has changed.',
                'location' => '',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $this->assertNull($announcement->fresh()->location);
    }

    public function test_a_location_longer_than_the_column_is_rejected(): void
    {
        $this->actingAs(User::factory()->secretary()->create())
            ->post(route('staff.announcements.store'), [
                'title' => 'Somewhere Specific',
                'body' => 'A notice with an over-long location.',
                'location' => str_repeat('a', 161),
            ])
            ->assertSessionHasErrors('location');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_the_staff_list_marks_a_pinned_notice(): void
    {
        Announcement::factory()->publishedAndPinned()->create(['title' => 'Pinned Notice']);
        Announcement::factory()->published()->create(['title' => 'Ordinary Notice']);

        $this->actingAs(User::factory()->secretary()->create())
            ->get(route('staff.announcements.index'))
            ->assertOk()
            ->assertSee('Pinned');
    }

    public function test_staff_can_save_an_announcement_with_a_category_and_a_photograph(): void
    {
        Storage::fake(Announcement::IMAGE_DISK);

        $this->actingAs(User::factory()->secretary()->create())
            ->post(route('staff.announcements.store'), [
                'title' => 'Barangay Health Drive',
                'body' => 'A free health screening will be held at the Barangay Hall.',
                'category' => 'health',
                'image' => UploadedFile::fake()->image('health-drive.jpg'),
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $announcement = Announcement::firstOrFail();

        $this->assertSame('health', $announcement->category);
        $this->assertStringStartsWith('announcements/', (string) $announcement->image_path);
        $this->assertTrue(
            Storage::disk(Announcement::IMAGE_DISK)->exists($announcement->image_path),
            'The uploaded photograph was not written to the announcements disk.',
        );
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        Storage::fake(Announcement::IMAGE_DISK);

        $this->actingAs(User::factory()->secretary()->create())
            ->post(route('staff.announcements.store'), [
                'title' => 'Document Attachment',
                'body' => 'This notice ships with a file that is not a photograph.',
                'image' => UploadedFile::fake()->create('notice.pdf', 32, 'application/pdf'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_editing_an_announcement_keeps_the_photograph_it_already_has(): void
    {
        Storage::fake(Announcement::IMAGE_DISK);

        $announcement = Announcement::factory()->withImage('announcements/kept.jpg')->create();
        Storage::disk(Announcement::IMAGE_DISK)->put('announcements/kept.jpg', 'photo');

        $this->actingAs(User::factory()->secretary()->create())
            ->put(route('staff.announcements.update', $announcement), [
                'title' => 'Updated Title',
                'body' => 'Updated body copy.',
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $this->assertSame('announcements/kept.jpg', $announcement->fresh()->image_path);
        $this->assertTrue(
            Storage::disk(Announcement::IMAGE_DISK)->exists('announcements/kept.jpg'),
            'Editing the copy of a notice must not remove its photograph.',
        );
    }

    public function test_uploading_a_new_photograph_replaces_the_file_that_was_there(): void
    {
        Storage::fake(Announcement::IMAGE_DISK);

        $announcement = Announcement::factory()->withImage('announcements/outdated.jpg')->create();
        Storage::disk(Announcement::IMAGE_DISK)->put('announcements/outdated.jpg', 'photo');

        $this->actingAs(User::factory()->secretary()->create())
            ->put(route('staff.announcements.update', $announcement), [
                'title' => 'Updated Title',
                'body' => 'Updated body copy.',
                'image' => UploadedFile::fake()->image('replacement.jpg'),
            ])
            ->assertRedirect(route('staff.announcements.index'));

        $imagePath = $announcement->fresh()->image_path;

        $this->assertNotSame('announcements/outdated.jpg', $imagePath);
        $this->assertTrue(
            Storage::disk(Announcement::IMAGE_DISK)->exists($imagePath),
            'The replacement photograph was not written to the announcements disk.',
        );
        $this->assertFalse(
            Storage::disk(Announcement::IMAGE_DISK)->exists('announcements/outdated.jpg'),
            'The photograph replaced by the upload was left behind on disk.',
        );
    }

    public function test_deleting_an_announcement_also_removes_its_photograph(): void
    {
        Storage::fake(Announcement::IMAGE_DISK);

        $announcement = Announcement::factory()->withImage('announcements/deleted.jpg')->create();
        Storage::disk(Announcement::IMAGE_DISK)->put('announcements/deleted.jpg', 'photo');

        $this->actingAs(User::factory()->secretary()->create())
            ->delete(route('staff.announcements.destroy', $announcement))
            ->assertRedirect(route('staff.announcements.index'));

        $this->assertFalse(
            Storage::disk(Announcement::IMAGE_DISK)->exists('announcements/deleted.jpg'),
            'The photograph of a deleted notice was left behind on disk.',
        );
    }

    #[DataProvider('staffProvider')]
    public function test_staff_can_open_the_edit_form(string $role): void
    {
        $announcement = Announcement::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('staff.announcements.edit', $announcement))
            ->assertOk()
            ->assertSee($announcement->title);
    }

    #[DataProvider('staffProvider')]
    public function test_staff_can_update_an_announcement_and_is_redirected_to_the_staff_index(string $role): void
    {
        $announcement = Announcement::factory()->unpublished()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->put(route('staff.announcements.update', $announcement), [
                'title' => 'Updated Title',
                'body' => 'Updated body copy.',
            ])
            ->assertRedirect(route('staff.announcements.index'))
            ->assertSessionHas('success');

        $this->assertSame('Updated Title', $announcement->fresh()->title);
    }

    #[DataProvider('staffProvider')]
    public function test_staff_can_delete_an_announcement_and_is_redirected_to_the_staff_index(string $role): void
    {
        $announcement = Announcement::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->delete(route('staff.announcements.destroy', $announcement))
            ->assertRedirect(route('staff.announcements.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    public function test_residents_and_kagawads_cannot_manage_announcements(): void
    {
        $announcement = Announcement::factory()->create();
        $resident = User::factory()->resident()->create();
        $kagawad = User::factory()->kagawad()->create();

        $this->actingAs($resident)->get(route('staff.announcements.index'))->assertForbidden();
        $this->actingAs($kagawad)->get(route('staff.announcements.index'))->assertForbidden();
        $this->actingAs($resident)
            ->post(route('staff.announcements.store'), ['title' => 'x', 'body' => 'y'])
            ->assertForbidden();
        $this->actingAs($kagawad)
            ->delete(route('staff.announcements.destroy', $announcement))
            ->assertForbidden();

        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
    }

    public function test_published_announcements_appear_on_the_public_portal(): void
    {
        Announcement::factory()->published()->create([
            'title' => 'Water Service Interruption Advisory',
        ]);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Water Service Interruption Advisory');
    }

    public function test_unpublished_announcements_do_not_appear_on_the_public_portal(): void
    {
        Announcement::factory()->unpublished()->create([
            'title' => 'Internal Draft Notice',
        ]);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Internal Draft Notice');
    }

    public function test_unpublished_announcements_do_not_appear_on_the_public_homepage(): void
    {
        Announcement::factory()->unpublished()->create([
            'title' => 'Homepage Draft Notice',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Homepage Draft Notice');
    }
}
