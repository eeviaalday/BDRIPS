<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "pinned_at" is what the public announcements page hangs its featured
     * notice on: the newest pinned notice is lifted out of the feed and shown
     * above it, so the barangay can keep one notice prominent without
     * republishing it. Null means not pinned, and the page simply omits the
     * section.
     *
     * "location" is the venue a notice is about - a court, a hall, a purok.
     * Null because most notices are not tied to a place.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('published_at');
            }

            if (! Schema::hasColumn('announcements', 'location')) {
                $table->string('location', 160)->nullable()->after('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['pinned_at', 'location'],
                fn (string $column): bool => Schema::hasColumn('announcements', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
