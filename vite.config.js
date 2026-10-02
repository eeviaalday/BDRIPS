import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // staff-icons.css is a SEPARATE input, not an @import inside app.css.
            //
            // It matters that it is separate. app.css is loaded by every page in
            // the application, including the public and resident ones, which are
            // not allowed to change. An @import at the top of app.css would put the
            // whole Font Awesome font on every public page and change the CSS those
            // pages are served.
            //
            // A separate entry means the emitted app.css is unchanged, and
            // layouts/app.blade.php — the staff shell — is the only thing that
            // asks for the icons file. layouts/public.blade.php and
            // layouts/guest.blade.php keep their original two-entry @vite call.
            input: [
                'resources/css/app.css',
                'resources/css/staff-icons.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
