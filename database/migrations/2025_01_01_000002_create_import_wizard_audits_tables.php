<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_wizard_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('panel_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('importer');
            $table->string('file_name');
            $table->string('disk');
            $table->string('upload_path');
            $table->string('file_path');
            $table->string('delimiter', 8)->default(',');
            $table->string('checksum', 64)->nullable();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('invalid_rows')->default(0);
            $table->json('column_map')->nullable();
            $table->json('options')->nullable();
            $table->json('error_summary')->nullable();
            $table->unsignedBigInteger('import_id')->nullable()->index();
            $table->unsignedBigInteger('successful_rows')->default(0);
            $table->unsignedBigInteger('failed_rows')->default(0);
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_wizard_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_id')->constrained('import_wizard_audits')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_wizard_audit_events');
        Schema::dropIfExists('import_wizard_audits');
    }
};
