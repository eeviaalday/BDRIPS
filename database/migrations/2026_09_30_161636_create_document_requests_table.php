<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A resident's request for a barangay document.
 *
 * The five statuses are the five things a Secretary can do with a request, in
 * the order they happen: it arrives submitted, is picked up for review, is
 * approved and issued, is collected in person, or is rejected. There is no
 * separate "completed" state because collecting the document IS the completion.
 *
 * There is no "abandoned" state either. The previous version had one, written
 * by a scheduled command that aged out unclaimed requests and then locked the
 * resident out. That policy is not part of the agreed behaviour, so neither is
 * the status.
 *
 * document_type_id rather than a free-text type, so a request always names a
 * type that exists. fee_amount is a snapshot of the type's fee at the moment of
 * submission, for the same reason the reference number is generated once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();

            // Shown to the resident and quoted in the office, so it is unique and
            // indexed for the lookup that finds it.
            $table->string('reference_number', 32)->unique();

            $table->text('purpose')->nullable();

            $table->string('status', 32)->default('submitted')->index();

            // The fee as quoted when the resident submitted, not looked up later.
            $table->decimal('fee_amount', 10, 2)->nullable();

            // The Secretary's own note, kept separate from the rejection reason
            // so a note is never shown to a resident as a refusal.
            $table->text('secretary_comment')->nullable();

            // Shown to the resident, so it must be present whenever status is
            // rejected. Enforced in the form request rather than the schema, so
            // the message can be a sentence rather than a constraint name.
            $table->text('rejection_reason')->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
