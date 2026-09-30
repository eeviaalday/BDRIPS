<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only record of who did what.
 *
 * Written for every state change and every sign-in, so an Admin can answer
 * "who approved this resident" and "who closed that batch" after the fact.
 *
 * Two deliberate choices:
 *
 * user_id is nullable AND nullOnDelete, because an audit trail that vanishes
 * with the account it describes is not an audit trail. user_name is therefore
 * also stored as a snapshot, so the log still reads correctly after the person
 * has been deleted.
 *
 * subject_type and subject_id point at whatever the entry is about - a resident
 * profile, a request, a batch - without a foreign key, because one pair of
 * columns has to cover every kind of thing and a real constraint cannot.
 *
 * No updated_at. An entry is written once and never edited; a column inviting
 * an edit would be a column nobody should use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Snapshot of the name at the time, so the trail survives deletion.
            $table->string('user_name')->nullable();

            // A short stable verb: login, logout, resident.verified,
            // request.approved, request.rejected, request.ready, request.claimed,
            // relief.received, user.role_changed, user.restricted,
            // user.unrestricted, settings.updated, sector.kagawad_assigned.
            $table->string('action', 64)->index();

            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // One sentence a human can read without decoding context.
            $table->text('description');

            // Before/after values for a change, and the reason for a decision.
            $table->json('context')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // The only timestamp, and indexed because the log is read newest
            // first, usually filtered to a recent window.
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
