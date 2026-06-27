<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptions = config('subscription.tables.subscriptions', 'merchant_subscriptions');

        Schema::table($subscriptions, function (Blueprint $table) use ($subscriptions): void {
            if (! Schema::hasColumn($subscriptions, 'failed_charge_count')) {
                $table->unsignedInteger('failed_charge_count')->default(0)->after('total_success_times');
            }
        });
    }

    public function down(): void
    {
        $subscriptions = config('subscription.tables.subscriptions', 'merchant_subscriptions');

        Schema::table($subscriptions, function (Blueprint $table) use ($subscriptions): void {
            if (Schema::hasColumn($subscriptions, 'failed_charge_count')) {
                $table->dropColumn('failed_charge_count');
            }
        });
    }
};
