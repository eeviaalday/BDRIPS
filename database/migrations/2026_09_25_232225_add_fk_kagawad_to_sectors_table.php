<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (! Schema::hasColumn('sectors', 'kagawad_user_id')) {
                return;
            }

            $table->foreign('kagawad_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (! Schema::hasColumn('sectors', 'kagawad_user_id')) {
                return;
            }

            $table->dropForeign(['kagawad_user_id']);
        });
    }
};
