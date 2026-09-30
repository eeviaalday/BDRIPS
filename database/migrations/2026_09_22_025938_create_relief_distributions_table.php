<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relief_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relief_batch_id')->constrained()->cascadeOnDelete();
            $table->string('household_number')->index();
            $table->foreignId('sector_id')->constrained();
            $table->string('status')->default('pending'); // pending | received
            $table->string('received_by_name')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['relief_batch_id', 'household_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relief_distributions');
    }
};
