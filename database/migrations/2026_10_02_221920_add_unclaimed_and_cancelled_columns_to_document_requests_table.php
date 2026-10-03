<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The unclaimed-document policy: three timestamps, no counter.
 *
 * WHY THREE TIMESTAMPS AND NOT A STRIKES COLUMN
 *
 * A resident's strike count and suspension are both DERIVED from the rows in
 * this table rather than stored beside them. That is a deliberate trade, and it
 * cuts both ways.
 *
 * For it: nothing can drift. There is no counter to fall out of step with the
 * requests behind it, so there is no state in which the audit log says a request
 * was excused and the counter still counts it, and no repair job to write. The
 * resident's standing is a question about their own rows, so it is answered by
 * asking about their own rows - `DocumentRequest::strikeCountFor()` - which is
 * also why excusing a strike lifts a suspension immediately and needs no second
 * write to propagate.
 *
 * Against it: the standing of a resident is read as a count of their rows rather
 * than read from one place, so the queries behind it have to stay correct as the
 * policy is retuned. `config/document-requests.php` is what they read, and the
 * tests pin the numbers rather than the implementation.
 *
 * The alternative - a strikes counter and a suspended_until on users - was
 * rejected for a second reason as well: users.restricted_until already exists and
 * is enforced by the role middleware as an ACCOUNT-WIDE freeze that ends the
 * session. Reusing it would have turned "this resident's document requests are
 * paused" into "this resident cannot sign in", which is a far larger punishment
 * than the policy describes, and it would have required editing User.php, which
 * the scope of this work does not permit.
 *
 * NULL MEANS NOT HAPPENED YET, ON ALL THREE
 *
 * A nullable timestamp says "never" with no row written at all, so a factory, a
 * seeder or a browser fixture that creates a request cannot accidentally mark it
 * unclaimed, excused or cancelled. There is no boolean defaulting to false sitting
 * beside a timestamp that can disagree with it.
 *
 * WHICH TIMESTAMP IS WHICH
 *
 * unclaimed_at is when a Secretary marked the request unclaimed, and it is the
 * strike date every window is measured from. unclaimed_excused_at being set is
 * what makes an unclaimed request stop counting - it holds the WHEN, while the
 * WHO and the required note live in the audit log's context, alongside the entry
 * for the mark itself. cancelled_at is when the resident withdrew the request
 * themselves; cancelling is not a strike and cannot clear one, so a cancellation
 * and an excuse are separate columns and neither writes the other.
 *
 * THE STATUS COLUMN IS NOT TOUCHED
 *
 * document_requests.status is already varchar(32) and unconstrained, and both new
 * values - unclaimed and cancelled - fit comfortably. They are added as model
 * constants, so this migration adds no enum, no CHECK and no default change, and
 * existing rows are untouched: their status is still one of the five values they
 * already had.
 *
 * Reversible on purpose. down() drops the index before the columns it covers,
 * because MySQL will not drop a column an index still refers to. The decisions
 * these timestamps recorded are NOT restored by a rollback, and cannot be: three
 * null columns describe a set of requests that is no longer unclaimed, excused or
 * cancelled, and inferring which ones those were would be a guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->timestamp('unclaimed_at')->nullable()->after('claimed_at');
            $table->timestamp('unclaimed_excused_at')->nullable()->after('unclaimed_at');
            $table->timestamp('cancelled_at')->nullable()->after('unclaimed_excused_at');

            // The staff queue's Unclaimed filter is `status = 'unclaimed'`, and the
            // overdue and strike-window predicates are ranges on unclaimed_at, so
            // every query this policy adds reads that pair and nothing else.
            $table->index(['status', 'unclaimed_at'], 'document_requests_status_unclaimed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            // Index first, then the columns underneath it. Reversing these two
            // statements makes MySQL stop the whole rollback with error 1091.
            $table->dropIndex('document_requests_status_unclaimed_at_index');

            $table->dropColumn(['unclaimed_at', 'unclaimed_excused_at', 'cancelled_at']);
        });
    }
};
