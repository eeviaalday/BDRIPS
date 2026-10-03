<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypted digital copies of documents: who was sent one, and the three settings
 * that govern how they are produced.
 *
 * WHY A TABLE OF DELIVERIES AND NOT A COLUMN ON document_requests
 *
 * A boolean on the request - "downloaded", "digital copy sent" - answers one
 * question with one value, and the moment the office wants to know how many times
 * a resident downloaded their certificate it cannot. This table records an EVENT,
 * so two downloads are two rows. That matters because the copy is generated fresh
 * each time against a password derived from the resident's own birthdate and
 * surname, and a resident who mistypes their password, or who changed it, will
 * come back and ask again: the honest answer to "have you sent this already" is
 * a list of when, not a yes/no that cannot show a repeat.
 *
 * The staff queue reads it with a `deliveries_count` subquery rather than by
 * touching these rows one at a time, so listing twenty requests costs the same
 * number of queries as listing one.
 *
 * THE PASSWORD IS NOT HERE, AND WILL NEVER BE
 *
 * The user password for a copy is derived from the resident's own birthdate and
 * the first letter of their surname. It is not a secret in the sense a stored
 * hash would be, but it is still not written down: a column beside the row would
 * put the key to every copy the barangay has ever issued on one table, readable
 * by anyone who can read the deliveries, and the whole point of encrypting the
 * file is defeated by a password sitting next to it.
 *
 * The owner password - the one that lifts editing and copying restrictions - is
 * generated per download and is discarded with the request that made it. It is
 * never persisted, never logged and never sent. It exists only inside the bytes
 * of the one file it encrypts, which means a copy cannot later be unlocked,
 * re-saved or stripped by anyone holding this database.
 *
 * ONE ROW PER SUCCESSFUL DOWNLOAD, NOT PER ATTEMPT
 *
 * The row is written after the PDF has been rendered and before the response is
 * sent. A failure to render therefore leaves no trace claiming a document was
 * sent, and the delivery count is a count of documents that were really produced.
 *
 * deleted_at IS ABSENT, AND user_id IS NOT A CASED COPY OF the REQUEST's owner
 *
 * A delivery is a fact about an event that happened, not a record somebody edits,
 * so it is never soft-deleted: an audit-shaped table that can hide rows is not one.
 * The foreign key cascades, though, so archiving or removing a request does take
 * its deliveries with it - otherwise a resident who asked for their data to go
 * would leave behind a table naming them, which is the opposite of the point.
 * The resident is recorded as the account that downloaded, not as a copy of the
 * request's owner, so the row says who actually took the file.
 *
 * THE THREE SETTINGS SEEDED HERE
 *
 * They are inserted rather than added to the settings screen as new inputs,
 * because SettingController refuses to create keys from a form: a key that exists
 * but is missing would render nowhere and quietly fall back to a default, which
 * for the footer setting would mean the digital-copy notice silently disappearing
 * off every certificate.
 *
 *   digital_copy_footer              The notice printed along the bottom of every
 *                                    copy. Configurable because a wrong notice on
 *                                    an official-looking document is worse than no
 *                                    notice, and the words that make a copy valid
 *                                    only with the wet signature are the office's
 *                                    to set.
 *   digital_copy_counts_as_claimed   Off by default, and the default matters. A
 *                                    download is not a collection: the resident may
 *                                    have closed the tab, or downloaded it for
 *                                    somebody else. Opting in is a deliberate
 *                                    decision by the office, recorded in the audit
 *                                    trail as such.
 *   barangay_logo_path               Empty, meaning the letterhead falls back to
 *                                    the logo already in public/. Stored as a path
 *                                    rather than an upload because this migration
 *                                    is the only schema change in scope and an
 *                                    upload handler is not.
 *
 * All three are stored in the "header" group alongside the existing rows. The
 * Settings screen groups by key name for layout and falls back to the stored
 * group for anything it does not recognise, so the group is presentation data and
 * is not read by anything.
 *
 * Reversible. down() drops the seeded settings first so a rollback cannot leave
 * keys pointing at a table that no longer exists.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const SETTINGS = [
        'digital_copy_footer',
        'digital_copy_counts_as_claimed',
        'barangay_logo_path',
    ];

    public function up(): void
    {
        Schema::create('document_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->default('download');
            $table->timestamps();

            // The staff filter is "downloaded, not yet collected", which asks every
            // row of a filtered page the same question, and the show page asks for
            // one request's downloads newest first. Both read this pair.
            $table->index(['document_request_id', 'id'], 'document_deliveries_request_id_id_index');

            // Counts by resident are a question the deliveries table is asked
            // directly - how many documents has this household been sent - so the
            // account side is indexed on its own rather than only as the second
            // column of the pair above.
            $table->index('user_id', 'document_deliveries_user_id_index');
        });

        DB::table('settings')->insertOrIgnore([
            [
                'key' => 'digital_copy_footer',
                'value' => 'This is a digitally generated copy. It is valid only when printed with the official signature and dry seal of the Punong Barangay.',
                'group' => 'header',
                'description' => 'Notice printed along the bottom of every digital copy.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'digital_copy_counts_as_claimed',
                // '0', not false: the column is a string and every other setting is
                // read with a cast in Setting, so an off value has to be the same
                // shape as an on one.
                'value' => '0',
                'group' => 'header',
                'description' => 'When on, a resident\'s first download marks the document collected. Off by default, because a download is not a collection.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'barangay_logo_path',
                'value' => '',
                'group' => 'header',
                'description' => 'Logo shown on the letterhead of digital copies. Leave empty to use the one in public/images.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', self::SETTINGS)->delete();

        Schema::dropIfExists('document_deliveries');
    }
};
