<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The national / voter ID number is no longer typed at signup.
     *
     * Step 5 asks for a photograph or scan of a valid ID instead, so a resident
     * without that number on hand - or one holding an ID the Secretary has not
     * issued a number for - can still register. The column stays, because the
     * twenty-five profiles that already hold a value must keep it, and because
     * the Secretary still reads it when the uploaded document is reviewed.
     *
     * The unique index is deliberately left in place. A nullable column with a
     * unique index accepts any number of NULLs, so the profiles that never
     * captured a number do not collide with one another, while two residents
     * who do both supply the same number still cannot both be stored.
     */
    public function up(): void
    {
        Schema::table('resident_profiles', function (Blueprint $table): void {
            $table->string('national_or_voter_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows created since the column was relaxed hold no number, so they are
        // filled with a distinct placeholder rather than colliding on the unique
        // index that goes back with the NOT NULL constraint.
        DB::table('resident_profiles')
            ->whereNull('national_or_voter_id')
            ->update(['national_or_voter_id' => DB::raw("'not provided'")]);

        Schema::table('resident_profiles', function (Blueprint $table): void {
            $table->string('national_or_voter_id')->nullable(false)->change();
        });
    }
};
