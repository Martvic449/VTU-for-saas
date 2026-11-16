<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40); $table->string('status', 24)->default('active'); $table->timestamps();
            $table->unique(['tenant_id', 'user_id']); $table->index(['user_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_user'); }
};
