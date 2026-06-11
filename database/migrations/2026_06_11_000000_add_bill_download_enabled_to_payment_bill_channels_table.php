<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payment_bill_channels')) {
            return;
        }

        Schema::table('payment_bill_channels', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_bill_channels', 'is_bill_download_enabled')) {
                $table->boolean('is_bill_download_enabled')
                    ->default(true)
                    ->after('is_enabled')
                    ->comment('是否开启下载账单');
                $table->index('is_bill_download_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('payment_bill_channels')) {
            return;
        }

        Schema::table('payment_bill_channels', function (Blueprint $table) {
            if (Schema::hasColumn('payment_bill_channels', 'is_bill_download_enabled')) {
                $table->dropColumn('is_bill_download_enabled');
            }
        });
    }
};
