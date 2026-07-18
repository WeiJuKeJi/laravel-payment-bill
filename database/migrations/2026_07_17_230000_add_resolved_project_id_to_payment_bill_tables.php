<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_bill_wechat_bills', function (Blueprint $table): void {
            $table->unsignedBigInteger('resolved_project_id')
                ->nullable()
                ->after('business_id')
                ->comment('外部业务系统解析出的项目ID缓存');
            $table->index(['resolved_project_id', 'trade_time', 'id'], 'idx_wechat_project_time');
        });

        Schema::table('payment_bill_alipay_bills', function (Blueprint $table): void {
            $table->unsignedBigInteger('resolved_project_id')
                ->nullable()
                ->after('business_id')
                ->comment('外部业务系统解析出的项目ID缓存');
            $table->index(['resolved_project_id', 'completed_time', 'id'], 'idx_alipay_project_time');
        });
    }

    public function down(): void
    {
        Schema::table('payment_bill_wechat_bills', function (Blueprint $table): void {
            $table->dropIndex('idx_wechat_project_time');
            $table->dropColumn('resolved_project_id');
        });

        Schema::table('payment_bill_alipay_bills', function (Blueprint $table): void {
            $table->dropIndex('idx_alipay_project_time');
            $table->dropColumn('resolved_project_id');
        });
    }
};
