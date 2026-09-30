<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity details captured during registration.
     *
     * The registration wizard asks for these before the account exists, so they
     * live on the user rather than on the resident profile: the profile is only
     * meaningful once there is something to verify, and the Barangay Secretary
     * cross-checks the master list against what was typed at signup.
     *
     * All four are nullable. The accounts that already exist were registered
     * before the wizard and have none of this on file, so a NOT NULL column
     * would either fail the migration or force a fabricated value. Registration
     * requires them going forward; older accounts keep null and the verification
     * screen is where they are asked for.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('name');
            $table->string('gender', 32)->nullable()->after('date_of_birth');
            $table->string('civil_status', 32)->nullable()->after('gender');
            $table->string('nationality', 64)->nullable()->default('Filipino')->after('civil_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'gender', 'civil_status', 'nationality']);
        });
    }
};
