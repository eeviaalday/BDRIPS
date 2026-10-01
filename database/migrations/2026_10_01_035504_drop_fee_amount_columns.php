<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barangay documents are free, so the two fee columns go.
 *
 * document_types.fee_amount was the going rate, and document_requests.fee_amount
 * was a copy of it taken at the moment a resident submitted, so that a later
 * price change could not restate what somebody was already quoted. With no fee
 * there is nothing to quote and nothing to snapshot, and keeping a nullable money
 * column on a row that is always meant to be zero is a field future code will
 * read and have to special-case.
 *
 * The old migrations are left exactly as they were written. They are the record
 * of how the schema got here, and rewriting them would make a fresh install
 * disagree with every database that already ran them.
 *
 * Reversible on purpose. down() puts both columns back with the definitions they
 * had - the request's nullable with no default, the type's NOT NULL defaulting to
 * zero - so a rollback returns the schema to a state the previous migrations can
 * still describe. The values are not restored, and cannot be: they are gone.
 *
 * There is deliberately no settings cleanup in here. The settings table never
 * held a fee; the eight rows it carries are all header fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->dropColumn('fee_amount');
        });

        Schema::table('document_types', function (Blueprint $table): void {
            $table->dropColumn('fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->decimal('fee_amount', 10, 2)->nullable();
        });

        Schema::table('document_types', function (Blueprint $table): void {
            $table->decimal('fee_amount', 10, 2)->default(0);
        });
    }
};
