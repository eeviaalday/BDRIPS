{{--
    The grid the staff figures sit in.

    Two columns on a phone, four from md up. Four is the number the dashboards
    actually have - the staff dashboard carries four figures and so does the User
    Accounts role row - so four is the width the row is designed against rather than
    a number picked from the scale.

    Two on a phone rather than one: a single card per row turns four figures into
    four screens of scrolling, and the figures are the reason to open the page.

    The child selector fills the gaps, so a caller cannot leave a hole by writing its
    own gap class on a wrapper.
--}}
<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-4 md:grid-cols-4 [&>*]:min-w-0']) }}>
    {{ $slot }}
</div>