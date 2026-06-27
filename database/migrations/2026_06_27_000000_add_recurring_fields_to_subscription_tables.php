<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptions = config('subscription.tables.subscriptions', 'merchant_subscriptions');
        $orders = config('subscription.tables.orders', 'subscription_orders');

        Schema::table($subscriptions, function (Blueprint $table) use ($subscriptions): void {
            if (! Schema::hasColumn($subscriptions, 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('is_internal');
            }
            if (! Schema::hasColumn($subscriptions, 'gwsr')) {
                $table->string('gwsr')->nullable()->after('is_recurring');
            }
            if (! Schema::hasColumn($subscriptions, 'total_success_times')) {
                $table->unsignedInteger('total_success_times')->default(0)->after('gwsr');
            }
            if (! Schema::hasColumn($subscriptions, 'recurring_period_type')) {
                $table->string('recurring_period_type')->nullable()->after('total_success_times');
            }
            if (! Schema::hasColumn($subscriptions, 'recurring_exec_times')) {
                $table->unsignedInteger('recurring_exec_times')->nullable()->after('recurring_period_type');
            }
        });

        Schema::table($orders, function (Blueprint $table) use ($orders): void {
            if (! Schema::hasColumn($orders, 'period_sequence')) {
                $table->unsignedInteger('period_sequence')->nullable()->after('billing_cycle');
            }
        });
    }

    public function down(): void
    {
        $subscriptions = config('subscription.tables.subscriptions', 'merchant_subscriptions');
        $orders = config('subscription.tables.orders', 'subscription_orders');

        Schema::table($subscriptions, function (Blueprint $table) use ($subscriptions): void {
            foreach (['recurring_exec_times', 'recurring_period_type', 'total_success_times', 'gwsr', 'is_recurring'] as $column) {
                if (Schema::hasColumn($subscriptions, $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table($orders, function (Blueprint $table) use ($orders): void {
            if (Schema::hasColumn($orders, 'period_sequence')) {
                $table->dropColumn('period_sequence');
            }
        });
    }
};
