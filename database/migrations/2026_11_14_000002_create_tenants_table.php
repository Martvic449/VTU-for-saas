<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id(); $table->string('name', 160); $table->string('slug', 50)->unique();
            $table->string('status', 24)->default('pending')->index(); $table->string('timezone', 64)->default('Africa/Lagos');
            $table->char('currency', 3)->default('NGN'); $table->json('settings')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tenants'); }
};
