<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The settings the public pages read.
 *
 * Every key already exists as a seeded row, so this screen edits values rather
 * than inventing keys. Editing by key is deliberate: the public templates read
 * Setting::get('barangay_name'), and a screen that let an Admin rename that key
 * would break the pages silently, since a missing key falls back to a default.
 *
 * The form is rendered from the rows, so a key added by a later migration shows
 * up here without a code change.
 */
class SettingController extends Controller
{
    public function index(): View
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get();

        return view('staff.settings.index', [
            'sections' => $this->sections($settings),
            // The published values, read from the rows rather than from the form,
            // because the preview is meant to answer "what is live right now" -
            // a preview that echoed the inputs would show unsaved text and claim
            // to be the public header.
            'published' => $settings->pluck('value', 'key'),
        ]);
    }

    /**
     * The sections the form is laid out in.
     *
     * The `group` column is not touched. All eight keys are stored in one group,
     * "header", and the public templates read them by key - so re-grouping the
     * rows would be a data change for a layout reason, and one that could move a
     * value out from under a page that iterates the column. The sections below
     * are presentation only and are built from the key names.
     *
     * A key that is not listed falls back to its own stored group, so a key added
     * by a later migration still appears on this page without a code change. That
     * is the same guarantee the previous single-panel layout gave.
     *
     * @param  Collection<int, Setting>  $settings
     * @return Collection<int, array{label: string, anchor: string, settings: Collection<int, Setting>}>
     */
    private function sections($settings)
    {
        $order = [
            'identity' => ['label' => 'Barangay identity', 'keys' => ['barangay_name', 'barangay_address']],
            'officials' => ['label' => 'Officials', 'keys' => ['punong_barangay', 'secretary_name']],
            'contact' => [
                'label' => 'Contact',
                'keys' => ['emergency_hotline', 'office_phone', 'office_email', 'office_facebook'],
            ],
        ];

        $byKey = $settings->keyBy('key');

        $sections = collect();

        foreach ($order as $anchor => $section) {
            $rows = collect($section['keys'])
                ->map(fn (string $key) => $byKey->get($key))
                ->filter()
                ->values();

            if ($rows->isNotEmpty()) {
                $sections->push([
                    'label' => $section['label'],
                    'anchor' => $anchor,
                    'settings' => $rows,
                ]);
            }
        }

        // Anything unlisted, under its own stored group.
        $placed = collect($order)->flatMap(fn (array $section): array => $section['keys'])->all();

        $leftovers = $settings->reject(fn (Setting $setting): bool => in_array($setting->key, $placed, true));

        foreach ($leftovers->groupBy('group') as $group => $rows) {
            $sections->push([
                'label' => ucfirst(str_replace('_', ' ', (string) $group)),
                'anchor' => 'other-'.$group,
                'settings' => $rows->values(),
            ]);
        }

        return $sections->values();
    }

    /**
     * Save every value on the form.
     *
     * Only keys that already exist are written, and only values that actually
     * changed are logged, so a screen that is opened and saved without edits
     * leaves the audit trail clean.
     */
    public function update(Request $request): RedirectResponse
    {
        $submitted = $request->validate([
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:2000'],
        ], [
            'values.required' => 'Nothing to save.',
        ])['values'];

        $changed = [];
        $unknown = [];

        DB::transaction(function () use ($submitted, &$changed, &$unknown): void {
            $known = Setting::query()->pluck('value', 'key');

            foreach ($submitted as $key => $value) {
                if (! $known->has($key)) {
                    $unknown[] = $key;

                    continue;
                }

                $value = $value === null ? '' : trim($value);

                if ((string) $known->get($key) === $value) {
                    continue;
                }

                Setting::query()->where('key', $key)->update(['value' => $value]);
                $changed[$key] = $value;
            }
        });

        if ($unknown !== []) {
            return back()->with('error', sprintf(
                'Ignored %d unknown setting%s: %s. Keys are not created from this form.',
                count($unknown),
                count($unknown) === 1 ? '' : 's',
                implode(', ', $unknown)
            ));
        }

        if ($changed === []) {
            return back()->with('success', 'Nothing changed.');
        }

        app(AuditLogger::class)->log(
            actor: $request->user(),
            action: 'settings.updated',
            subject: null,
            description: sprintf(
                '%d setting%s updated: %s',
                count($changed),
                count($changed) === 1 ? '' : 's',
                implode(', ', array_keys($changed))
            ),
            context: $changed,
        );

        return back()->with('success', sprintf(
            '%d setting%s saved.',
            count($changed),
            count($changed) === 1 ? '' : 's'
        ));
    }
}
