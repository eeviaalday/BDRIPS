<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            // Grouped in PHP from one read, because the page renders one panel
            // per group and querying per group would be a round trip each.
            'groups' => $settings
                ->groupBy('group')
                ->map(fn ($rows, $group): array => [
                    'label' => ucfirst(str_replace('_', ' ', (string) $group)),
                    'settings' => $rows,
                ])
                ->values(),
        ]);
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
