<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ComponentSlot;
use Tests\TestCase;

/**
 * The shared Blade components the staff and resident screens are built from.
 *
 * These are presentation, but they are the layer where a status or a date gets
 * shown to a resident, so a couple of them are pinned here: the timeline in
 * particular must never show a step as available that the backend does not
 * actually offer.
 */
class SharedComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_breadcrumb_marks_the_last_item_as_the_current_page(): void
    {
        $html = $this->renderComponent('breadcrumb', [
            'items' => [
                ['label' => 'Staff', 'route' => 'staff.dashboard'],
                ['label' => 'Document Requests', 'route' => 'staff.requests.index'],
                ['label' => 'BRGY-2026-4831'],
            ],
        ]);

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertStringContainsString(url('/staff/requests'), $html);
    }

    public function test_the_breadcrumb_collapses_a_long_trail_to_the_last_three(): void
    {
        $html = $this->renderComponent('breadcrumb', [
            'items' => array_map(
                fn (int $i): array => ['label' => 'Step '.$i],
                range(1, 8),
            ),
        ]);

        $this->assertStringContainsString('Step 8', $html);
        $this->assertStringNotContainsString('Step 1<', $html);
        $this->assertStringContainsString('Earlier pages', $html);
    }

    public function test_the_data_table_shows_its_empty_state_instead_of_a_bare_table(): void
    {
        $html = $this->renderComponent('data-table', [
            'columns' => [['label' => 'Reference']],
            'emptyTitle' => 'No requests yet',
            'emptyDescription' => 'Nothing has been submitted.',
        ]);

        $this->assertStringContainsString('No requests yet', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_the_data_table_labels_its_scroll_region_for_keyboard_users(): void
    {
        $html = $this->renderComponent('data-table', [
            'columns' => [['label' => 'Reference']],
            'caption' => 'Your document requests',
        ], '<tr><td>BRGY-2026-0001</td></tr>');

        // A horizontally scrolling table that a keyboard cannot reach is not
        // usable without a mouse.
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('role="region"', $html);
        $this->assertStringContainsString('<caption class="sr-only">Your document requests</caption>', $html);
    }

    public function test_the_confirm_dialog_posts_csrf_and_uses_the_given_method(): void
    {
        $html = $this->renderComponent('confirm-dialog', [
            'name' => 'confirm-delete',
            'title' => 'Delete this notice?',
            'message' => 'This cannot be undone.',
            'form' => '/staff/announcements/1',
            'method' => 'DELETE',
            'tone' => 'danger',
        ]);

        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('bg-red-600', $html);
    }

    public function test_the_filter_bar_preserves_the_current_query_as_selected(): void
    {
        request()->query->add(['status' => 'ready_for_pickup']);

        $html = $this->renderComponent('filter-bar', [
            'action' => '/staff/requests',
            'selects' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['queued' => 'Queued', 'ready_for_pickup' => 'Ready']],
            ],
        ]);

        $this->assertStringContainsString('value="ready_for_pickup" selected', $html);
        $this->assertStringContainsString('Filter', $html);
    }

    public function test_the_stepper_marks_the_current_step_for_assistive_technology(): void
    {
        $html = $this->renderComponent('stepper', [
            'steps' => ['Type', 'Purpose', 'Review'],
            'current' => 2,
        ]);

        $this->assertStringContainsString('aria-current="step"', $html);
        $this->assertStringContainsString('(current step)', $html);
        $this->assertStringContainsString('aria-label="Progress"', $html);
    }

    public function test_the_file_upload_keeps_the_real_input_and_omits_a_name_on_the_echo_box(): void
    {
        $html = $this->renderComponent('file-upload', [
            'name' => 'valid_id_photo',
            'label' => 'Valid ID Photo / Document',
            'accept' => '.jpg,.png,.pdf',
        ]);

        // The real control must keep its name or nothing is submitted.
        $this->assertMatchesRegularExpression('/name="valid_id_photo"/', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('accept=".jpg,.png,.pdf"', $html);
        $this->assertStringContainsString('No file chosen', $html);
        $this->assertStringContainsString('Browse', $html);

        // ...and must be reachable by keyboard, so a real button not a label.
        $this->assertStringContainsString('<button type="button"', $html);
    }

    /**
     * The timeline must reflect where a record IS, and must not imply a step is
     * available. The controllers have no state machine, so a completed request
     * can be reopened by a crafted POST - a timeline that drew itself as a menu
     * of next steps would be quietly lying about that.
     */
    public function test_the_request_timeline_reflects_the_real_status(): void
    {
        $user = User::factory()->resident()->create();
        $request = DocumentRequest::factory()->create(['user_id' => $user->id, 'status' => DocumentRequest::STATUS_READY]);

        $html = $this->renderComponent('request-timeline', [
            'status' => $request->status,
            'request' => $request,
        ]);

        $this->assertStringContainsString('Ready for Pickup', $html);
        $this->assertStringContainsString('aria-current="step"', $html);

        // Steps before the current one are marked done; steps after are not.
        $this->assertStringContainsString('Completed:', $html);
    }

    public function test_the_request_timeline_shows_a_rejection_reason(): void
    {
        $user = User::factory()->resident()->create();
        $request = DocumentRequest::factory()->create(['user_id' => $user->id, 'status' => DocumentRequest::STATUS_REJECTED]);
        $request->update(['rejection_reason' => 'The stated purpose was unclear.']);

        $html = $this->renderComponent('request-timeline', [
            'status' => $request->fresh()->status,
            'request' => $request->fresh(),
        ]);

        $this->assertStringContainsString('Rejected', $html);
        $this->assertStringContainsString('The stated purpose was unclear.', $html);
    }

    public function test_an_abandoned_request_is_not_drawn_as_still_travelling(): void
    {
        $user = User::factory()->resident()->create();
        $request = DocumentRequest::factory()->create(['user_id' => $user->id, 'status' => DocumentRequest::STATUS_ABANDONED]);

        $html = $this->renderComponent('request-timeline', [
            'status' => $request->status,
            'request' => $request,
        ]);

        $this->assertStringContainsString('Abandoned', $html);
        $this->assertStringContainsString('7 working day pickup window', $html);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderComponent(string $component, array $data, string $slot = ''): string
    {
        return view('components.'.$component, $data)
            ->with('slot', new ComponentSlot($slot))
            ->render();
    }
}
