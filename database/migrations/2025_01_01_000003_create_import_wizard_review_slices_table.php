<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_wizard_review_slices', function (Blueprint $table): void {
            $table->id();
            $table->string('run_key', 64);
            $table->unsignedInteger('slice_index');
            $table->unsignedBigInteger('first_row');
            $table->unsignedInteger('rows');
            $table->unsignedInteger('invalid');
            $table->json('errors_by_column');
            $table->json('sample');
            $table->json('preview')->nullable();
            $table->unique(['run_key', 'slice_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_wizard_review_slices');
    }
};
