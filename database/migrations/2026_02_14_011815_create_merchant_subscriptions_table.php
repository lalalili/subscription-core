<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create(config('subscription.tables.subscriptions', 'merchant_subscriptions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId(config('subscription.owner.foreign_key', 'merchant_id'))->constrained(config('subscription.owner.table', 'merchants'))->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained(config('subscription.tables.plans', 'subscription_plans'));
            $table->string('billing_cycle');
            $table->unsignedInteger('price');
            $table->string('status');
            $table->boolean('is_internal')->default(false);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.subscriptions', 'merchant_subscriptions'));
    }
};
