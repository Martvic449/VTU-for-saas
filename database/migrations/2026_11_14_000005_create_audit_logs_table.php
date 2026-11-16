<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id(); $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 120)->index(); $table->string('subject_type', 160)->nullable(); $table->string('subject_id', 100)->nullable();
            $table->text('reason')->nullable(); $table->string('ip_address', 45)->nullable(); $table->string('user_agent', 1000)->nullable();
            $table->json('metadata')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']); $table->index(['subject_type', 'subject_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('audit_logs'); }
};
