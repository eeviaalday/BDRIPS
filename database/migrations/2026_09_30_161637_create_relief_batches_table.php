<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A relief batch: one distribution event, open then closed.
 *
 * A batch is created by the Secretary or an Admin, and closed when the
 * distribution is finished. Closing is a status change rather than a delete, so
 * the record of what was handed out and who collected it survives.
 *
 * items_included is a list of goods, which is genuinely a list rather than a
 * sentence, so it is JSON. It is nullable because a batch is often described in
 * prose instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relief_batches', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            $table->json('items_included')->nullable();

            $table->date('distribution_date')->nullable();

            // active | closed. A closed batch stays readable so a Kagawad can
            // still see the history of what their sector collected.
            $table->string('status', 16)->default('active')->index();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relief_batches');
    }
};
