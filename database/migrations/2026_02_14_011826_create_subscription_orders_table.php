<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create(config('subscription.tables.orders', 'subscription_orders'), function (Blueprint $table): void {
            $table->id();
            $table->string('number', 10)->unique();
            $table->foreignId(config('subscription.owner.foreign_key', 'merchant_id'))->constrained(config('subscription.owner.table', 'merchants'))->cascadeOnDelete();
            $table->foreignId('merchant_subscription_id')->constrained(config('subscription.tables.subscriptions', 'merchant_subscriptions'));
            $table->foreignId('subscription_plan_id')->constrained(config('subscription.tables.plans', 'subscription_plans'));
            $table->string('billing_cycle');
            $table->unsignedInteger('amount');
            $table->unsignedTinyInteger('payment_status')->default(1);
            $table->string('payment_status_message')->nullable();
            $table->datetime('payment_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('subscription.tables.orders', 'subscription_orders'));
    }
};
