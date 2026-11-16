<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('hostname', 253)->unique(); $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable(); $table->char('verification_token_hash', 64)->nullable(); $table->timestamp('verification_expires_at')->nullable(); $table->string('tls_status', 24)->default('pending'); $table->timestamps();
            $table->index(['tenant_id', 'is_primary']);
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_domains'); }
};
