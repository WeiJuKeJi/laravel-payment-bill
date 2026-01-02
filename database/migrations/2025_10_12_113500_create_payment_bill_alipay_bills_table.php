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
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payment_channel_id')->comment('支付渠道ID');
            $table->date('bill_date')->comment('账单日期');
            $table->string('alipay_trade_no', 64)->nullable()->comment('支付宝交易号');
            $table->string('merchant_order_no', 128)->nullable()->comment('商户订单号');
            $table->string('biz_type', 64)->nullable()->comment('业务类型');
            $table->string('goods_name', 255)->nullable()->comment('商品名称');
            $table->timestamp('created_time')->nullable()->comment('创建时间');
            $table->timestamp('completed_time')->nullable()->comment('完成时间');
            $table->string('store_id', 128)->nullable()->comment('门店编号');
            $table->string('store_name', 255)->nullable()->comment('门店名称');
            $table->string('operator', 128)->nullable()->comment('操作员');
            $table->string('terminal_id', 128)->nullable()->comment('终端号');
            $table->string('counterparty_account', 255)->nullable()->comment('对方账户');
            $table->decimal('order_amount', 16, 2)->nullable()->comment('订单金额（元）');
            $table->decimal('merchant_settlement_amount', 16, 2)->nullable()->comment('商家实收（元）');
            $table->decimal('alipay_red_envelope_amount', 16, 2)->nullable()->comment('支付宝红包（元）');
            $table->decimal('point_deduction_amount', 16, 2)->nullable()->comment('集分宝抵扣（元）');
            $table->decimal('alipay_discount_amount', 16, 2)->nullable()->comment('支付宝优惠（元）');
            $table->decimal('merchant_discount_amount', 16, 2)->nullable()->comment('商家优惠（元）');
            $table->decimal('coupon_amount', 16, 2)->nullable()->comment('券核销金额（元）');
            $table->string('coupon_name', 255)->nullable()->comment('券名称');
            $table->decimal('merchant_red_envelope_amount', 16, 2)->nullable()->comment('商家红包消费金额（元）');
            $table->decimal('card_amount', 16, 2)->nullable()->comment('卡消费金额（元）');
            $table->string('refund_request_no', 255)->nullable()->comment('退款批次号/请求号');
            $table->decimal('service_fee_amount', 16, 2)->nullable()->comment('服务费（元）');
            $table->decimal('profit_sharing_amount', 16, 2)->nullable()->comment('分润（元）');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->index(['payment_channel_id', 'bill_date'], 'payment_bill_alipay_bills_channel_date_index');
            $table->index('alipay_trade_no', 'payment_bill_alipay_bills_trade_no_index');
            $table->index('merchant_order_no', 'payment_bill_alipay_bills_merchant_order_index');
            $table->unique([
                'payment_channel_id',
                'bill_date',
                'alipay_trade_no',
                'merchant_order_no',
                'biz_type',
                'refund_request_no',
            ], 'payment_bill_alipay_bills_unique_record');
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
