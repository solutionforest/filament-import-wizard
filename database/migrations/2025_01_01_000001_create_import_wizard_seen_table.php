<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_wizard_seen', function (Blueprint $table): void {
            $table->string('run_key', 64);
            $table->string('hash', 32);
            $table->primary(['run_key', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_wizard_seen');
    }
};
