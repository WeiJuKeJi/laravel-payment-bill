<?php

namespace WeiJuKeJi\PaymentBill\Models;

use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 微信账单明细模型。
 */
class WechatBill extends Model
{
    use HasFactory;
    use Filterable;

    protected $table = 'payment_bill_wechat_bills';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_channel_id',
        'trade_time',
        'appid',
        'mch_id',
        'special_mch_id',
        'device_id',
        'wechat_transaction_id',
        'out_trade_no',
        'user_open_id',
        'trade_type',
        'trade_state',
        'payment_bank',
        'currency',
        'total_amount',
        'settlement_amount',
        'voucher_amount',
        'wechat_refund_no',
        'out_refund_no',
        'refund_amount',
        'refund_voucher_amount',
        'refund_type',
        'refund_status',
        'goods_name',
        'goods_info',
        'fee_amount',
        'fee_rate',
        'refund_apply_amount',
        'is_order_associated',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'trade_time' => 'datetime',
        'total_amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'voucher_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_voucher_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'refund_apply_amount' => 'decimal:2',
        'is_order_associated' => 'boolean',
    ];

    /**
     * 关联支付渠道。
     */
    public function paymentChannel(): BelongsTo
    {
        return $this->belongsTo(PaymentChannel::class);
    }

    /**
     * 挂载筛选器。
     */
    public function modelFilter()
    {
        return $this->provideFilter(\WeiJuKeJi\PaymentBill\ModelFilters\WechatBillFilter::class);
    }
}
