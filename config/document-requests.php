<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document Request Strikes, Suspension and Pickup Windows
    |--------------------------------------------------------------------------
    |
    | Every number in the unclaimed-document policy lives here, and nowhere
    | else. The refusal a resident reads, the warning on their request page, the
    | badge a Secretary sees and the tests that pin the behaviour all read
    | these keys rather than a copy of a figure, so changing a value here
    | changes the whole system at once and cannot leave one screen telling a
    | resident a different number from the one the server enforces.
    |
    | A separate file rather than a block in config/bdrpis.php because that file
    | holds public presentation values - photographs, copy, the steps on the
    | homepage - and nothing in it decides whether somebody may file a request.
    | This file is policy, and policy is the one thing that must be impossible
    | to misread as decoration.
    |
    */

    'ready_days_before_unclaimed' => 14,

    /*
     | How many un-excused unclaimed requests inside the strike window suspend
     | new requests. Reaching it pauses submissions; it does not close the
     | account, and it does not touch anything else the resident can do.
     */
    'strikes_to_suspend' => 3,

    /*
     | How far back an un-excused unclaimed request still counts as a strike.
     | A request unclaimed longer ago than this has aged out and no longer
     | contributes, so a resident is never permanently penalised for something
     | they did years ago.
     */
    'strike_window_days' => 180,

    /*
     | How long the suspension lasts, measured from the most recent strike that
     | put the resident over the threshold.
     |
     | The end date is computed from this and shown to the resident, so nobody
     | has to guess when they may apply again, and it is shown to staff on the
     | resident's Request reliability card for the same reason. A value that is
     | not stated anywhere is a value a resident has to phone the barangay to
     * ask about.
     */
    'suspension_days' => 90,

    /*
     | How long an unclaimed request keeps holding one of the resident's open
     | request slots.
     |
     | This is deliberately shorter than the suspension. The slot hold is about
     | the queue: the document was made ready and not collected, so it still
     | occupies the resident's allowance until the office stops holding it. Once
     | the hold lapses the slot frees itself with no scheduled job and no staff
     | action, which is why the cap has no date dimension of its own and needs
     | no cron to stay honest.
     */
    'unclaimed_slot_hold_days' => 30,

];
