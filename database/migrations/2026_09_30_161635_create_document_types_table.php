<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The document types the barangay can issue.
 *
 * These were four hardcoded entries in config/bdrpis.php, which meant the list
 * could be displayed but never changed: a barangay that wanted to add a
 * certificate, or retire one, needed a developer and a deployment. They are a
 * table now so the Admin settings screen can genuinely manage them.
 *
 * "key" is the stable identifier stored on a request, so renaming a label never
 * orphans the requests that used it. The four original keys are seeded with
 * their original wording.
 *
 * fee_amount is copied onto a request at submit time rather than read from here
 * later, so a fee change does not silently restate what a resident was already
 * quoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();

            // Stable identifier, referenced by document_requests.
            $table->string('key', 64)->unique();

            $table->string('label');
            $table->text('description')->nullable();

            $table->decimal('fee_amount', 10, 2)->default(0);

            // The public services page shows an icon and a tone per type; both
            // are display-only and nullable so a new type needs no artwork.
            $table->string('icon', 64)->nullable();
            $table->string('tone', 32)->nullable();

            // Retiring a type hides it from the chooser without touching the
            // requests already filed under it.
            $table->boolean('is_active')->default(true);

            // Explicit ordering, because the public catalogue is a reading
            // order the barangay chose rather than an alphabetical one.
            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
