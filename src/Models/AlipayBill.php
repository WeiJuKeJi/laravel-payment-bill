<?php

namespace WeiJuKeJi\PaymentBill\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use WeiJuKeJi\PaymentBill\Concerns\Reconcilable;

/**
 * 支付宝账单明细模型。
 */
class AlipayBill extends Model
{
    use HasFactory;
    use Filterable;
    use Reconcilable;

    protected $table = 'payment_bill_alipay_bills';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_channel_id',
        'bill_date',
        'alipay_trade_no',
        'merchant_order_no',
        'biz_type',
        'goods_name',
        'created_time',
        'completed_time',
        'store_id',
        'store_name',
        'operator',
        'terminal_id',
        'counterparty_account',
        'order_amount',
        'merchant_settlement_amount',
        'alipay_red_envelope_amount',
        'point_deduction_amount',
        'alipay_discount_amount',
        'merchant_discount_amount',
        'coupon_amount',
        'coupon_name',
        'merchant_red_envelope_amount',
        'card_amount',
        'refund_request_no',
        'service_fee_amount',
        'profit_sharing_amount',
        'remark',
        // 对账相关字段
        'reconciliation_status',
        'business_type',
        'business_id',
        'reconciled_at',
        'reconciliation_amount_diff',
        'reconciliation_remark',
        'reconciled_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'bill_date' => 'date',
        'created_time' => 'datetime',
        'completed_time' => 'datetime',
        'order_amount' => 'decimal:2',
        'merchant_settlement_amount' => 'decimal:2',
        'alipay_red_envelope_amount' => 'decimal:2',
        'point_deduction_amount' => 'decimal:2',
        'alipay_discount_amount' => 'decimal:2',
        'merchant_discount_amount' => 'decimal:2',
        'coupon_amount' => 'decimal:2',
        'merchant_red_envelope_amount' => 'decimal:2',
        'card_amount' => 'decimal:2',
        'service_fee_amount' => 'decimal:2',
        'profit_sharing_amount' => 'decimal:2',
        // 对账相关字段
        'reconciled_at' => 'datetime',
        'reconciliation_amount_diff' => 'decimal:2',
    ];

    /**
     * 关联支付渠道。
     */
    public function paymentChannel(): BelongsTo
    {
        return $this->belongsTo(PaymentChannel::class);
    }

    /**
     * 多态关联业务订单。
     */
    public function business(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 挂载筛选器。
     */
    public function modelFilter()
    {
        return $this->provideFilter(\WeiJuKeJi\PaymentBill\ModelFilters\AlipayBillFilter::class);
    }
}
