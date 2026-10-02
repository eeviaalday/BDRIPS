<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft archiving for resident accounts.
 *
 * THE COLUMNS ARE ON users, NOT ON resident_profiles
 *
 * An archived account is an account that cannot sign in. That is a property of the
 * login, not of the household, and a resident_profiles column would have left the
 * one thing archiving is *for* - the credential - working exactly as before, with
 * the block living in some other table and reachable by forgetting to check it.
 * Every archive predicate in the codebase is therefore a whereHas() on this table
 * or a check on the authenticated User, never a column read off a profile.
 *
 * NULL MEANS NOT ARCHIVED, ON ALL FOUR
 *
 * There is no archived boolean. A nullable timestamp says "not archived" with no
 * row written at all, so a bulk insert that forgets the flag - a factory, a seeder,
 * the registration wizard - cannot accidentally archive anybody, and
 * `whereNull('archived_at')` is the whole of the "is this account live" question.
 * A boolean defaulting to false would have the same meaning and one more column
 * that can disagree with the timestamp beside it.
 *
 * WHY FOUR COLUMNS AND NOT ONE
 *
 * archived_at says when. archived_by says which staff member did it, which is the
 * question an audit is asked first and the one that cannot be reconstructed
 * afterwards. archive_reason is a closed list of four because "why" is asked
 * constantly and free text would be unanswerable; archive_note is the optional
 * sentence behind it, because "Moved away" alone does not record that a family
 * relocated to Taguig in March.
 *
 * archived_by IS NULLABLE AND NULLS ON DELETE
 *
 * It points at users from inside users. Restricting an account - the thing
 * archiving is for - is not deletion, but the Captain can delete an account, and a
 * row whose archived_by blocked that delete would turn a cleanup into a failed
 * migration. nullOnDelete means the archive survives losing its author, which is
 * the right way round: the record of the decision outlives the person who made it.
 *
 * THE HOUSEHOLD IS NOT RELEASED
 *
 * resident_profiles.archived_at does not exist and this migration adds nothing to
 * that table, so an archived profile keeps its row and its household_number stays
 * spoken for. That is what makes a later restore safe from a collision: the number
 * cannot have been reissued while the holder was away.
 *
 * Reversible on purpose. down() drops the index, then the foreign key, then the
 * columns - in that order, because MySQL will not drop a column an index or a
 * constraint still refers to. The archived records themselves are NOT restored by
 * a rollback: four null columns describe a set of accounts that is no longer
 * archived, and inferring which ones those were would be a guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('restricted_until');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archive_reason', 50)->nullable()->after('archived_by');
            $table->text('archive_note')->nullable()->after('archive_reason');

            // Every archived-aware query in the app filters on this column and the
            // directory is paged through it, so it is worth the index. The composite
            // order puts archived_at first because that is the equality/IS NULL
            // side of every predicate built on it; id follows so MySQL can satisfy
            // the ORDER BY id from the index rather than sorting afterwards.
            $table->index(['archived_at', 'id'], 'users_archived_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_archived_at_id_index');

            // Drops the foreign key and the column together, so archived_by is
            // deliberately absent from the dropColumn list below - naming it in
            // both is a second DROP COLUMN for a column that is already gone, and
            // MySQL stops the whole rollback with error 1091 partway through.
            $table->dropConstrainedForeignId('archived_by');

            $table->dropColumn(['archived_at', 'archive_reason', 'archive_note']);
        });
    }
};
