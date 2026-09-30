<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One household's place in one relief batch.
 *
 * This is the row the Kagawad ticks off, and it is the authorisation anchor for
 * the whole Kagawad area: a Kagawad may only act on rows whose sector_id is a
 * sector they are assigned to via sectors.kagawad_user_id. The anchor is a
 * column here rather than a join through a profile, so the checklist is a
 * single indexed read and cannot drift with profile edits.
 *
 * The compound unique means a household appears at most once per batch, which
 * is what makes "mark received" idempotent and stops a second Kagawad
 * collecting the same household by accident.
 *
 * household_number rather than a user_id, because relief follows the household
 * and a household is what the resident looks their own record up by. It is
 * indexed because that lookup is the resident's Relief Transparency page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relief_distributions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('relief_batch_id')->constrained()->cascadeOnDelete();

            $table->string('household_number', 50)->index();

            $table->foreignId('sector_id')->constrained();

            // pending | received
            $table->string('status', 16)->default('pending');

            // The name signed for the goods, which is often not the account
            // holder: a relative collects on a resident's behalf.
            $table->string('received_by_name')->nullable();

            // Who recorded it. Kept so a disputed collection can be traced, and
            // nullOnDelete so removing a staff account keeps the record.
            $table->foreignId('logged_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();

            $table->unique(['relief_batch_id', 'household_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relief_distributions');
    }
};
