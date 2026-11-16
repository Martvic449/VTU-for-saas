<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('owner_type', 24)->default('tenant');
            $table->unsignedBigInteger('owner_id');
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'currency'], 'wallet_owner_currency_unique');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('account_type', 24);
            $table->string('normal_side', 8);
            $table->char('currency', 3)->default('NGN');
            $table->boolean('is_control_account')->default(false);
            $table->timestamps();
        });

        Schema::create('wallet_ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ledger_journals', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->string('event_type', 64)->index();
            $table->string('source_type', 80)->nullable();
            $table->string('source_id', 100)->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 16)->default('posted');
            $table->text('description')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('ledger_journals')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('side', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('NGN');
            $table->string('memo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ledger_account_id', 'created_at']);
        });

        Schema::create('deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('gateway', 64);
            $table->string('gateway_reference', 150)->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 24)->default('initiated')->index();
            $table->json('verification_metadata')->nullable();
            $table->foreignId('ledger_journal_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 64);
            $table->string('event_id', 180);
            $table->string('event_type', 100);
            $table->string('deposit_reference', 100)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload_redacted')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'event_id']);
        });

        Schema::create('providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 120);
            $table->string('adapter', 120);
            $table->string('environment', 16)->default('sandbox');
            $table->string('status', 24)->default('inactive')->index();
            $table->text('credentials_encrypted')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_catalogue', function (Blueprint $table): void {
            $table->id();
            $table->string('service_code', 64)->unique();
            $table->string('category', 40)->index();
            $table->string('name', 120);
            $table->string('status', 24)->default('inactive')->index();
            $table->json('validation_schema')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalogue_id')->constrained('service_catalogue')->cascadeOnDelete();
            $table->string('provider_product_code', 100);
            $table->string('status', 24)->default('inactive');
            $table->json('mapping')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'service_catalogue_id', 'provider_product_code'], 'provider_service_map_unique');
        });

        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalogue_id')->constrained('service_catalogue')->cascadeOnDelete();
            $table->foreignId('provider_service_id')->nullable()->constrained('provider_services')->nullOnDelete();
            $table->unsignedBigInteger('cost_minor');
            $table->unsignedBigInteger('sell_minor');
            $table->unsignedBigInteger('floor_minor')->nullable();
            $table->unsignedBigInteger('ceiling_minor')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'service_catalogue_id', 'status']);
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_catalogue_id')->constrained('service_catalogue')->restrictOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('idempotency_key', 120);
            $table->char('request_fingerprint', 64);
            $table->string('status', 24)->default('created')->index();
            $table->unsignedBigInteger('sell_minor');
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->json('input_snapshot');
            $table->json('price_snapshot');
            $table->string('provider_reference', 150)->nullable();
            $table->foreignId('ledger_journal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('settlement_journal_id')->nullable()->constrained('ledger_journals')->restrictOnDelete();
            $table->text('failure_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['wallet_id', 'idempotency_key'], 'wallet_idempotency_unique');
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('transaction_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('status', 24)->default('submitted');
            $table->string('provider_reference', 150)->nullable();
            $table->json('request_redacted')->nullable();
            $table->json('response_redacted')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'attempt_number']);
        });

        Schema::create('api_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('environment', 16)->default('sandbox');
            $table->json('scopes')->nullable();
            $table->json('ip_allowlist')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('prefix', 24)->unique();
            $table->char('secret_hash', 64);
            $table->json('scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_id', 80)->unique();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['api_client_id', 'created_at']);
        });

        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 20)->default('pending')->index();
            $table->string('calculation_version', 40);
            $table->foreignId('reversal_of_id')->nullable()->constrained('commissions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['transaction_id', 'beneficiary_user_id', 'calculation_version'], 'commission_once_unique');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('risk_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 100)->nullable();
            $table->string('rule_code', 80)->index();
            $table->string('severity', 16)->default('medium');
            $table->json('signals_redacted')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 40);
            $table->string('status', 20)->default('running')->index();
            $table->json('summary')->nullable();
            $table->text('exception_summary')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('api_client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->text('secret_encrypted');
            $table->json('events');
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event_id', 100);
            $table->string('event_type', 100);
            $table->json('payload');
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['webhook_endpoint_id', 'event_id']);
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 100);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('billing_interval', 16)->default('month');
            $table->json('entitlements')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('trial');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('kyc_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('status', 24)->default('pending')->index();
            $table->string('provider_reference', 150)->nullable();
            $table->json('evidence_metadata')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_type', 120)->index();
            $table->string('aggregate_type', 100)->nullable();
            $table->string('aggregate_id', 100)->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('subject', 180);
            $table->string('status', 24)->default('open')->index();
            $table->string('priority', 16)->default('normal');
            $table->text('description');
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach ([
            'support_tickets', 'reconciliation_runs', 'risk_events', 'notifications', 'commissions', 'api_requests', 'api_keys', 'outbox_events', 'kyc_records', 'subscriptions', 'plans',
            'webhook_deliveries', 'webhook_endpoints', 'api_clients', 'transaction_attempts',
            'transactions', 'pricing_rules', 'provider_services', 'service_catalogue', 'providers',
            'payment_events', 'deposits', 'ledger_entries', 'ledger_journals',
            'wallet_ledger_accounts', 'ledger_accounts', 'wallets',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
