<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Sector;
use App\Models\Setting;
use App\View\Components\PublicLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicController extends Controller
{
    /**
     * Homepage: hero, featured document services, and featured announcements.
     *
     * The remaining public areas each have their own page now, so nothing else
     * is rendered inline here. The services list and the activities/about/demographics
     * copy come from config, and the contact details come from the PublicLayout
     * component, so this action only needs the announcement query.
     */
    public function welcome(): View
    {
        return view('public.welcome', [
            // The featured grid is three across on desktop, so more than three
            // notices would only produce a row the reader cannot see. The limit
            // lives on the model because the demo seeder tops the table up to
            // the same number.
            'announcements' => $this->publishedAnnouncements()
                ->take(Announcement::FEATURED_ON_HOMEPAGE)
                ->get(),
        ]);
    }

    /**
     * Supported document services, plus the step-by-step request walkthrough.
     */
    public function services(): View
    {
        return view('public.services');
    }

    /**
     * About the barangay: history, vision, mission, demographics, and community activities.
     *
     * The "Find Us" panel offers a copy-to-clipboard button and a Facebook link
     * beside the map, so the two contact channels it needs come from the same
     * settings row as the contact page rather than being typed into the view.
     */
    public function about(): View
    {
        return view('public.about', [
            'officeEmail' => Setting::get('office_email', 'floria123@yahoo.com'),
            'officeFacebook' => Setting::get('office_facebook', PublicLayout::FACEBOOK_PAGE),
        ]);
    }

    /**
     * Contact details, office hours guidance, and a pointer to relief transparency.
     */
    public function contact(): View
    {
        return view('public.contact', $this->contactDetails());
    }

    public function directory(): View
    {
        return view('public.directory', [
            'sectors' => Sector::with('kagawad')->orderBy('sector_name')->get(),
            'officials' => $this->officials(),
        ]);
    }

    /**
     * The announcements archive: a featured pinned notice, then the feed, with
     * the search, category pills and sort the filter bar offers.
     *
     * The pinned notice is the newest one the staff have pinned, and it is
     * lifted out of the feed rather than repeated in it. The sidebar's
     * hotlines come from the settings table so a number is published once and
     * shown in both places; the schedules are static display values.
     */
    public function announcements(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', 'all');
        $sort = $request->query('sort') === 'oldest' ? 'oldest' : 'newest';

        // Read through the published query rather than off the column alone, so
        // a notice that is pinned but not yet published - or dated ahead of
        // itself - cannot be featured to the public.
        $pinned = $this->publishedAnnouncements()
            ->whereNotNull('pinned_at')
            ->reorder('pinned_at', 'desc')
            ->with('createdBy')
            ->first();

        $query = $this->publishedAnnouncements()
            ->when($pinned, fn (Builder $builder) => $builder->whereKeyNot($pinned->getKey()))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->when(
                ($categories = $this->filterCategories($filter)) !== null,
                fn (Builder $builder) => $builder->whereIn('category', $categories)
            );

        // reorder() rather than oldest()/latest(): the shared published query
        // already orders by publication date, and adding a second sort on top of
        // it would leave the first one in charge. The id is the tiebreaker so
        // two notices published in the same minute cannot swap places between
        // one page and the next.
        $query->reorder('published_at', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id');

        return view('public.announcements', [
            'announcements' => $query->paginate(6)->withQueryString(),
            'pinned' => $pinned,
            'search' => $search,
            'filter' => $filter,
            'sort' => $sort,
            'hotlines' => $this->hotlines(),
            'schedules' => config('bdrpis.schedules', []),
        ]);
    }

    /**
     * The stored categories a filter pill covers, or null when the pill is the
     * "all" reset or names a filter that no longer exists - both of which mean
     * "do not narrow the feed".
     *
     * @return array<int, string>|null
     */
    private function filterCategories(string $filter): ?array
    {
        $buckets = config('bdrpis.announcement_filters', []);

        if ($filter === 'all' || ! isset($buckets[$filter]['categories'])) {
            return null;
        }

        $categories = $buckets[$filter]['categories'];

        return $categories === [] ? null : $categories;
    }

    /**
     * The sidebar hotline rows, with each office's number taken from the
     * settings table so it is published once and cannot drift.
     *
     * Two offices that share a number - the case today, where the office line
     * and the hotline are the same setting - collapse into one row. Printing
     * one number twice under two office names reads as two lines to call and
     * sends people to the wrong one.
     *
     * @return array<int, array{office: string, detail: string, number: string}>
     */
    private function hotlines(): array
    {
        $rows = array_filter(array_map(function (array $hotline): ?array {
            $number = trim((string) Setting::get((string) $hotline['setting'], ''));

            return $number === '' ? null : [
                'office' => $hotline['office'],
                'detail' => $hotline['detail'],
                'number' => $number,
            ];
        }, config('bdrpis.hotlines', [])));

        $seen = [];

        return array_values(array_filter($rows, function (array $row) use (&$seen): bool {
            if (in_array($row['number'], $seen, true)) {
                return false;
            }

            $seen[] = $row['number'];

            return true;
        }));
    }

    /**
     * Published announcements, newest first.
     *
     * Only notices whose published_at is set and not in the future are visible
     * to the public; drafts and scheduled posts stay hidden.
     */
    private function publishedAnnouncements(): Builder
    {
        return Announcement::published()->latest('published_at');
    }

    /**
     * Contact details read from the settings table, with the same fallbacks the
     * header and footer have always used.
     *
     * The hotline is not in here: the office line and the hotline are the same
     * number for this barangay, so the public pages print one contact number.
     * The hotline itself is still read for the officials directory, where it is
     * presented as the public information line.
     *
     * @return array<string, string>
     */
    private function contactDetails(): array
    {
        return [
            'barangayName' => Setting::get('barangay_name', 'Barangay Linasin, Zone 1'),
            'barangayAddress' => Setting::get('barangay_address', 'Zone 1, Bangued, Abra'),
            'officePhone' => Setting::get('office_phone', '09663320659'),
            'officeEmail' => Setting::get('office_email', 'floria123@yahoo.com'),
            'officeFacebook' => Setting::get('office_facebook', PublicLayout::FACEBOOK_PAGE),
        ];
    }

    /**
     * @return array{punong_barangay: string, secretary: string, hotline: string}
     */
    private function officials(): array
    {
        return [
            'punong_barangay' => Setting::get('punong_barangay', 'Hon. [Punong Barangay]'),
            'secretary' => Setting::get('secretary_name', 'Barangay Secretary'),
            'hotline' => Setting::get('emergency_hotline', '09663320659'),
        ];
    }
}
