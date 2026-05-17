<?php

namespace WeiJuKeJi\PaymentBill\ModelFilters;

use Carbon\Carbon;
use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;

/**
 * payment_bill_alipay_bills 表的筛选器，提供常用查询条件。
 */
class AlipayBillFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected $blacklist = ['page', 'per_page', 'order'];

    /**
     * 按支付渠道筛选。
     *
     * @param  mixed  $channelIds
     */
    public function paymentChannel($channelIds): self
    {
        $ids = array_filter(Arr::wrap($channelIds));

        if (empty($ids)) {
            return $this;
        }

        return $this->whereIn('payment_channel_id', $ids);
    }

    /**
     * 精确匹配账单日期。
     */
    public function billDate(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (! $parsed) {
            return $this;
        }

        return $this->whereDate('bill_date', $parsed->toDateString());
    }

    /**
     * 账单日期范围筛选。
     */
    public function dateBetween($range): self
    {
        $dates = Arr::wrap($range);

        if (count($dates) < 2) {
            return $this;
        }

        $from = $this->parseDate($dates[0] ?? null);
        $to = $this->parseDate($dates[1] ?? null);

        if (! $from || ! $to) {
            return $this;
        }

        return $this->whereBetween('bill_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * 指定起始日期。
     */
    public function dateFrom(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (! $parsed) {
            return $this;
        }

        return $this->whereDate('bill_date', '>=', $parsed->toDateString());
    }

    /**
     * 指定结束日期。
     */
    public function dateTo(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (! $parsed) {
            return $this;
        }

        return $this->whereDate('bill_date', '<=', $parsed->toDateString());
    }

    /**
     * 按业务类型筛选。支付宝业务明细中 biz_type 为中文（交易/退款），保持原值匹配。
     *
     * @param  mixed  $types
     */
    public function bizType($types): self
    {
        $values = array_values(array_filter(array_map(
            static fn ($value) => is_string($value) ? trim($value) : $value,
            Arr::wrap($types)
        )));

        if (empty($values)) {
            return $this;
        }

        return $this->whereIn('biz_type', $values);
    }

    /**
     * 按页面交易类型过滤（payment→交易, refund→退款）。
     *
     * @param  mixed  $kinds
     */
    public function transactionKind($kinds): self
    {
        $values = array_values(array_unique(array_filter(array_map('strtolower', Arr::wrap($kinds)))));

        if (empty($values) || count($values) > 1) {
            return $this;
        }

        if ($values[0] === 'refund') {
            return $this->where('biz_type', '退款');
        }

        if ($values[0] === 'payment') {
            return $this->where('biz_type', '交易');
        }

        return $this;
    }

    /**
     * 按对账状态过滤。
     *
     * @param  mixed  $statuses
     */
    public function reconciliationStatus($statuses): self
    {
        $values = array_filter(array_map('strtolower', Arr::wrap($statuses)));

        if (empty($values)) {
            return $this;
        }

        return $this->whereIn('reconciliation_status', $values);
    }

    /**
     * 精确匹配支付宝交易号。
     */
    public function alipayTradeNo(string $tradeNo): self
    {
        $tradeNo = trim($tradeNo);

        if ($tradeNo === '') {
            return $this;
        }

        return $this->where('alipay_trade_no', $tradeNo);
    }

    /**
     * 精确匹配商户订单号。
     */
    public function merchantOrderNo(string $orderNo): self
    {
        $orderNo = trim($orderNo);

        if ($orderNo === '') {
            return $this;
        }

        return $this->where('merchant_order_no', $orderNo);
    }

    /**
     * 模糊搜索关键字段。
     */
    public function keywords(string $keywords): self
    {
        $keywords = trim($keywords);

        if ($keywords === '') {
            return $this;
        }

        $like = sprintf('%%%s%%', $keywords);

        return $this->where(function ($query) use ($like) {
            $query->where('alipay_trade_no', 'ilike', $like)
                ->orWhere('merchant_order_no', 'ilike', $like)
                ->orWhere('goods_name', 'ilike', $like)
                ->orWhere('coupon_name', 'ilike', $like)
                ->orWhere('remark', 'ilike', $like);
        });
    }

    /**
     * 日期解析，异常返回 null。
     */
    protected function parseDate(?string $date): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
