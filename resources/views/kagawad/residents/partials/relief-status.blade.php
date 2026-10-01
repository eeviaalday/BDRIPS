{{--
    One resident's relief status for the selected batch, for both renderings.

    This is a partial rather than inline markup because the phone cards and the
    table would otherwise be two copies of the same three-way decision, and two
    copies is how a card ends up saying "Collected" on a row the table calls
    "Not yet received".

    The three cases, and why there are three:

      Received  - on this batch's checklist and collected. The collected date is
                  shown, because "have they had it" and "when" are different
                  questions and the date is the whole point of the record.
      Not yet   - on this batch's checklist, not collected yet.
      Not on    - verified, but absent from this batch's checklist. Deliberately not
                  labelled "Not yet received": that phrase is a claim about the
                  batch, and somebody who is not on it cannot be outstanding on it.
      Unverified- no relief status at all, whatever the batch says. An unverified
                  profile is not on a distribution, so a status here would be a
                  claim the batch cannot support.

    $relief_status and $relief_claimed_at arrive as subselects on the row (see
    KagawadSectorResidents), so this reads attributes and never queries.
--}}
@php
    $onChecklist = $profile->isVerified()
        && array_key_exists('relief_status', $profile->getAttributes())
        && $profile->getAttribute('relief_status') !== null;
    $received = $onChecklist && $profile->getAttribute('relief_status') === 'received';
@endphp

@if (! $profile->isVerified())
    <span class="text-2xs text-slate-500">No relief status</span>
@elseif (! $onChecklist)
    <x-staff.status-badge status="neutral" label="Not on this batch" size="sm" />
@elseif ($received)
    <span class="inline-flex flex-col items-start gap-0.5">
        <x-staff.status-badge status="collected" label="Received" size="sm" />
        @if ($profile->getAttribute('relief_claimed_at'))
            <span class="text-2xs text-slate-500">
                {{ \Illuminate\Support\Carbon::parse($profile->getAttribute('relief_claimed_at'))->format('j M Y') }}
            </span>
        @endif
    </span>
@else
    <x-staff.status-badge status="pending" label="Not yet received" size="sm" />
@endif
