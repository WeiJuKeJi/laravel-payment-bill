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
        Schema::create('payment_bill_alipay_bills', function (Blueprint $table) {
            // 主键
            $table->bigIncrements('id');

            // 基础信息
            $table->unsignedBigInteger('payment_channel_id')->comment('支付渠道ID');
            $table->date('bill_date')->comment('账单日期');

            // 订单信息
            $table->string('alipay_trade_no', 64)->nullable()->comment('支付宝交易号');
            $table->string('merchant_order_no', 128)->nullable()->comment('商户订单号');
            $table->string('biz_type', 64)->nullable()->comment('业务类型');

            // 商品信息
            $table->string('goods_name', 255)->nullable()->comment('商品名称');

            // 时间信息
            $table->timestamp('created_time')->nullable()->comment('创建时间');
            $table->timestamp('completed_time')->nullable()->comment('完成时间');

            // 门店信息
            $table->string('store_id', 128)->nullable()->comment('门店编号');
            $table->string('store_name', 255)->nullable()->comment('门店名称');
            $table->string('operator', 128)->nullable()->comment('操作员');
            $table->string('terminal_id', 128)->nullable()->comment('终端号');

            // 账户信息
            $table->string('counterparty_account', 255)->nullable()->comment('对方账户');

            // 金额相关
            $table->decimal('order_amount', 16, 2)->nullable()->comment('订单金额（元）');
            $table->decimal('merchant_settlement_amount', 16, 2)->nullable()->comment('商家实收（元）');

            // 优惠相关
            $table->decimal('alipay_red_envelope_amount', 16, 2)->nullable()->comment('支付宝红包（元）');
            $table->decimal('point_deduction_amount', 16, 2)->nullable()->comment('集分宝抵扣（元）');
            $table->decimal('alipay_discount_amount', 16, 2)->nullable()->comment('支付宝优惠（元）');
            $table->decimal('merchant_discount_amount', 16, 2)->nullable()->comment('商家优惠（元）');
            $table->decimal('coupon_amount', 16, 2)->nullable()->comment('券核销金额（元）');
            $table->string('coupon_name', 255)->nullable()->comment('券名称');
            $table->decimal('merchant_red_envelope_amount', 16, 2)->nullable()->comment('商家红包消费金额（元）');
            $table->decimal('card_amount', 16, 2)->nullable()->comment('卡消费金额（元）');

            // 退款相关
            $table->string('refund_request_no', 255)->nullable()->comment('退款批次号/请求号');

            // 手续费和分润
            $table->decimal('service_fee_amount', 16, 2)->nullable()->comment('服务费（元）');
            $table->decimal('profit_sharing_amount', 16, 2)->nullable()->comment('分润（元）');

            // 备注
            $table->text('remark')->nullable()->comment('备注');

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
            $table->index(['payment_channel_id', 'bill_date'], 'idx_channel_date');
            $table->index('alipay_trade_no', 'idx_alipay_trade_no');
            $table->index('merchant_order_no', 'idx_merchant_order_no');

            // 唯一索引 - 防止重复数据
            $table->unique([
                'payment_channel_id',
                'bill_date',
                'alipay_trade_no',
                'merchant_order_no',
                'biz_type',
                'refund_request_no',
            ], 'idx_unique_record');

            // 索引 - 对账相关
            $table->index('reconciliation_status', 'idx_alipay_reconciliation_status');
            $table->index(['business_type', 'business_id'], 'idx_alipay_business');
            $table->index('reconciled_at', 'idx_alipay_reconciled_at');
            $table->index(['reconciliation_status', 'reconciled_at'], 'idx_alipay_status_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_bill_alipay_bills');
    }
};
