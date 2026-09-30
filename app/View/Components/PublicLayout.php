<?php

namespace App\View\Components;

use App\Models\Setting;
use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    /**
     * The barangay's official Facebook page. It lives here rather than in the
     * view because the footer and the contact page both link to it, and a share
     * link that changed in two places would eventually point at two pages.
     */
    public const FACEBOOK_PAGE = 'https://www.facebook.com/share/1CG4UcEVAy/';

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.public', [
            'barangayName' => Setting::get('barangay_name', 'Barangay Linasin, Zone 1'),
            'barangayAddress' => Setting::get('barangay_address', 'Zone 1, Bangued, Abra'),
            'officePhone' => Setting::get('office_phone', '09663320659'),
            'officeEmail' => Setting::get('office_email', 'floria123@yahoo.com'),
            'officeFacebook' => Setting::get('office_facebook', self::FACEBOOK_PAGE),
            'footerPhoto' => $this->footerPhoto(),
        ]);
    }

    /**
     * The photograph behind the footer's right-hand edge, resolved through the
     * same public image map the pages use.
     *
     * Checked against the disk here rather than trusted, so a photo that was
     * never added to public/ degrades to the neutral artwork instead of
     * leaving a broken image inside the banner.
     */
    private function footerPhoto(): string
    {
        $photograph = config('bdrpis.images.footer');

        if (filled($photograph) && is_file(public_path($photograph))) {
            return asset($photograph);
        }

        return asset(config('bdrpis.image_fallbacks.footer', config('bdrpis.image_fallbacks.hero')));
    }
}
