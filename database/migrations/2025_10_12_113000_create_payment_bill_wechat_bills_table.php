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
        Schema::create('payment_bill_wechat_bills', function (Blueprint $table) {
            // 主键
            $table->bigIncrements('id');

            // 基础信息
            $table->unsignedBigInteger('payment_channel_id')->comment('支付渠道ID');
            $table->timestamp('trade_time')->nullable()->comment('交易时间');

            // 微信相关字段
            $table->string('appid', 64)->nullable()->comment('公众账号ID');
            $table->string('mch_id', 64)->nullable()->comment('商户号');
            $table->string('special_mch_id', 64)->nullable()->comment('特约商户号');
            $table->string('device_id', 128)->nullable()->comment('设备号');

            // 订单信息
            $table->string('wechat_transaction_id', 128)->nullable()->comment('微信订单号');
            $table->string('out_trade_no', 128)->nullable()->comment('商户订单号');
            $table->string('user_open_id', 128)->nullable()->comment('用户标识');
            $table->string('trade_type', 32)->nullable()->comment('交易类型');
            $table->string('trade_state', 32)->nullable()->comment('交易状态');
            $table->string('payment_bank', 64)->nullable()->comment('付款银行');
            $table->string('currency', 16)->nullable()->comment('货币种类');

            // 金额相关
            $table->decimal('total_amount', 16, 2)->nullable()->comment('订单金额');
            $table->decimal('settlement_amount', 16, 2)->nullable()->comment('应结订单金额');
            $table->decimal('voucher_amount', 16, 2)->nullable()->comment('代金券金额');

            // 退款相关
            $table->string('wechat_refund_no', 128)->nullable()->comment('微信退款单号');
            $table->string('out_refund_no', 128)->nullable()->comment('商户退款单号');
            $table->decimal('refund_amount', 16, 2)->nullable()->comment('退款金额');
            $table->decimal('refund_voucher_amount', 16, 2)->nullable()->comment('充值券退款金额');
            $table->string('refund_type', 64)->nullable()->comment('退款类型');
            $table->string('refund_status', 64)->nullable()->comment('退款状态');
            $table->decimal('refund_apply_amount', 16, 2)->nullable()->comment('申请退款金额');

            // 商品信息
            $table->string('goods_name', 256)->nullable()->comment('商品名称');
            $table->text('goods_info')->nullable()->comment('商户数据包');

            // 手续费相关
            $table->decimal('fee_amount', 16, 2)->nullable()->comment('手续费金额');
            $table->string('fee_rate', 32)->nullable()->comment('费率');

            // 对账相关字段
            $table->string('reconciliation_status', 20)->default('pending')->comment('对账状态');
            $table->string('business_type', 255)->nullable()->comment('业务模型类型');
            $table->unsignedBigInteger('business_id')->nullable()->comment('业务订单ID');
            $table->timestamp('reconciled_at')->nullable()->comment('对账完成时间');
            $table->decimal('reconciliation_amount_diff', 16, 2)->default(0)->comment('对账金额差异');
            $table->text('reconciliation_remark')->nullable()->comment('对账备注');
            $table->string('reconciled_by', 64)->nullable()->comment('对账操作人');

            // 时间戳
            $table->timestamps();

            // 索引 - 业务查询
            $table->index(['payment_channel_id', 'trade_time'], 'idx_channel_time');
            $table->index('wechat_transaction_id', 'idx_wechat_transaction_id');
            $table->index('out_trade_no', 'idx_out_trade_no');

            // 索引 - 对账相关
            $table->index('reconciliation_status', 'idx_wechat_reconciliation_status');
            $table->index(['business_type', 'business_id'], 'idx_wechat_business');
            $table->index('reconciled_at', 'idx_wechat_reconciled_at');
            $table->index(['reconciliation_status', 'reconciled_at'], 'idx_wechat_status_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_bill_wechat_bills');
    }
};
