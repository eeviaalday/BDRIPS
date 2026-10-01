@props([
    // A Kagawad with no sector has to be able to tell "you have not been given a
    // sector yet" from "there is no relief going out", because those are two
    // different conversations with the barangay office. Hence a titled empty
    // state rather than a bare "nothing to show you".
    //
    // The wording names the Captain, because the Captain is who actually does this
    // and a Kagawad being told "ask the office" has to work out for themselves
    // which part of the office. "Captain" is the real title for the admin role -
    // see ROLES.md, owner decision 1 - so this is no longer inventing a role.
    'title' => 'No sector assigned yet',
    'description' => 'You have not been assigned a sector yet. The Barangay Captain (Admin) assigns sectors from Sectors & Kagawad. Until then there is nothing to show here.',
    'icon' => 'lock',
])

{{--
    The unassigned-Kagawad state. One component, used by the dashboard, the relief
    index and the resident list, because the sentence used to be written in each
    place separately and the copies had already drifted.

    The sentence names the Captain and the page it names, but carries NO link, and
    that combination is deliberate rather than an oversight:

      - Naming it is right. The Captain is the admin role and assigning a Kagawad to
        a sector is one of the five admin-only destinations (ROLES.md, owner
        decision 1). A Kagawad told "ask the office" has to guess which desk; a
        Kagawad told "the Captain assigns sectors" knows who to ask for.

      - Linking it would be wrong. /staff/sectors sits behind role:admin, so this
        account is refused with 403. A link that renders and then fails is worse
        than no link, because the empty state's whole job is to tell the Kagawad
        what is missing without sending them somewhere they cannot go.

    This component previously asserted the opposite - that "Captain" was not a role
    and must never appear. That was correct when it was written and is now wrong:
    the Captain is the Admin. ROLES.md records the correction, and
    KagawadFieldAreaTest asserts that the Captain is named here while the
    admin-only page is still not linked.

    The icon is a lock rather than the map-pin that would read more cheerfully,
    because the state is genuinely "you cannot see anything yet" and the map-pin
    would promise a location that is not there.
--}}
<x-staff.empty-state :icon="$icon" :title="$title" :description="$description" />
