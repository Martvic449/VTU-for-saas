<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('direction', 12);
            $table->string('status', 16)->default('pending')->index();
            $table->string('reason', 500);
            $table->string('evidence_reference', 180)->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ledger_journal_id')->nullable()->constrained('ledger_journals')->restrictOnDelete();
            $table->string('decision_reason', 500)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['wallet_id','status','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('wallet_adjustments'); }
};
