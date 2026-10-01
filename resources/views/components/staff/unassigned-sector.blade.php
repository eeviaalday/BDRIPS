@props([
    // A Kagawad with no sector has to be able to tell "you have not been given a
    // sector yet" from "there is no relief going out", because those are two
    // different conversations with the barangay office. Hence a titled empty
    // state rather than a bare "nothing to show you".
    'title' => 'No sector assigned yet',
    'description' => 'Ask the barangay office to assign you a sector. Once assigned, your relief checklist appears here.',
    'icon' => 'lock',
])

{{--
    The unassigned-Kagawad state. One component, used by both the dashboard and the
    relief index, because the sentence was previously written twice and the two
    copies had already drifted: the dashboard said the office assigns sectors
    "from Sectors & Kagawad" and the relief page said "A Captain assigns a Kagawad
    to a sector from Sectors & Kagawad".

    Both were wrong in the same way, and wrong in a way a Kagawad could check.
    /staff/sectors sits behind role:admin, so that page is not merely awkward for
    a Kagawad to be told about - it is one they cannot open, and following the
    advice leads to a 403. "Captain" is also not a role in this system at all:
    User::roles() is admin, secretary, kagawad, resident.

    So this carries no link on purpose. The only action it can offer is talking to
    the office, and a button that pretends to do something here would either be
    dead or would point somewhere the Kagawad is refused.

    The icon is a lock rather than the map-pin that would read more cheerfully,
    because the state is genuinely "you cannot see anything yet" and the map-pin
    would promise a location that is not there.
--}}
<x-staff.empty-state :icon="$icon" :title="$title" :description="$description" />
