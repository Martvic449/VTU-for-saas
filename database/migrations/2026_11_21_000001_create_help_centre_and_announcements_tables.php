<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('knowledge_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('knowledge_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content');
            $table->string('status', 20)->default('draft')->index();
            $table->string('audience', 20)->default('both')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->index(['status', 'audience', 'published_at']);
        });

        Schema::create('platform_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('message', 1000);
            $table->string('audience', 20)->default('both')->index();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_dismissible')->default(true);
            $table->timestamps();
            $table->index(['status', 'audience', 'starts_at', 'ends_at']);
        });

        Schema::create('knowledge_article_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestamps();
            $table->unique(['article_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_article_feedback');
        Schema::dropIfExists('platform_announcements');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('knowledge_categories');
    }
};
