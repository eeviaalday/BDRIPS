<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The public announcement card needs a category tag and a featured
     * photograph, neither of which the original table carried. Both are
     * nullable so every existing row keeps working: a post saved without a
     * category renders the default tag, and one without a photograph renders
     * the category's configured image.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'category')) {
                $table->string('category', 50)->nullable()->after('body');
            }

            if (! Schema::hasColumn('announcements', 'image_path')) {
                $table->string('image_path')->nullable()->after('category');
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
                ['category', 'image_path'],
                fn (string $column): bool => Schema::hasColumn('announcements', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
