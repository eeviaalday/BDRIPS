{{--
    The site favicon. One partial, included once by every document.

    WHY A PARTIAL AND NOT FIVE COPIES. There are five places in this application
    that open a <head>: layouts/public (which also carries every resident page),
    layouts/guest, layouts/app (staff and Kagawad), and the two standalone
    auth pages, login and register, which are whole documents of their own. Five
    copies of two <link> tags is five places for the next change to reach only
    some of them, and a favicon that is right on six pages and missing on one is
    not something anyone notices until a user reports it.

    WHY THE FILE IS COPIED RATHER THAN LINKED. The logo is
    public/images/Zone 1_logo.png and the space in that name has to be encoded as
    %20 in every URL that reaches it. That works, but it means every page's markup
    carries an escaped path for a brand mark, and a filename with a space is a
    thing to trip over later. public/favicon.png is a byte-identical copy. The
    original is not renamed, not moved and not deleted — the sidebar, the top bar
    and two auth pages all still read it from where it was.

    WHY ?v= IS THE FILE'S OWN MODIFICATION TIME. A favicon is cached far more
    stubbornly than anything else in a page: browsers hold on to it for days and
    will not revalidate unless told to. Replacing the image without changing the
    URL is the single most common way a favicon appears not to have updated. The
    mtime is used rather than a hand-written version string because it changes by
    itself when the image does, which is the failure this is here to prevent. It
    also needs no remembering: nobody has to remember to bump a number.

    The mtime is read once per request and cached in a static, because this partial
    renders on every page and a filemtime() per <link> would be a stat() per link.
--}}
@php
    static $version = null;

    $version ??= (string) (@filemtime(public_path('favicon.png')) ?: filemtime(public_path('images/Zone 1_logo.png')));
@endphp
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ $version }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $version }}">
