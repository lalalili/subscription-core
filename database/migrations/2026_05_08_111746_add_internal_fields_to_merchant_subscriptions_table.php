<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $tableName = config('subscription.tables.subscriptions', 'merchant_subscriptions');

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'is_internal')) {
                $table->boolean('is_internal')->default(false)->after('status');
            }

            if (Schema::hasColumn($tableName, 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        $tableName = config('subscription.tables.subscriptions', 'merchant_subscriptions');

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (Schema::hasColumn($tableName, 'expires_at')) {
                $table->timestamp('expires_at')->nullable(false)->change();
            }

            if (Schema::hasColumn($tableName, 'is_internal')) {
                $table->dropColumn('is_internal');
            }
        });
    }
};
