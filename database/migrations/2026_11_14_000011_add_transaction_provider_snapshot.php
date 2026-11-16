<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('provider_id')->nullable()->after('service_catalogue_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_service_id')->nullable()->after('provider_id')->constrained('provider_services')->restrictOnDelete();
            $table->string('provider_product_code', 100)->nullable()->after('provider_service_id');
        });
    }
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('provider_service_id');
            $table->dropConstrainedForeignId('provider_id');
            $table->dropColumn('provider_product_code');
        });
    }
};
