<?php

namespace WeiJuKeJi\PaymentBill\ModelFilters;

use Carbon\Carbon;
use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;

/**
 * payment_bill_wechat_bills 表的筛选器。
 */
class WechatBillFilter extends ModelFilter
{
    /**
     * @var array<int, string>
     */
    protected $blacklist = ['page', 'per_page', 'order'];

    /**
     * 按支付渠道过滤。
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
     * 按交易日期过滤。
     */
    public function tradeDate(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (! $parsed) {
            return $this;
        }

        return $this->whereDate('trade_time', $parsed->toDateString());
    }

    /**
     * 按交易时间范围过滤。
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

        return $this->whereBetween('trade_time', [$from->startOfDay(), $to->endOfDay()]);
    }

    /**
     * 交易时间起始。
     */
    public function dateFrom(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (! $parsed) {
            return $this;
        }

        return $this->where('trade_time', '>=', $parsed->startOfDay());
    }

    /**
     * 交易时间结束。
     */
    public function dateTo(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (! $parsed) {
            return $this;
        }

        return $this->where('trade_time', '<=', $parsed->endOfDay());
    }

    /**
     * 按交易类型过滤。
     *
     * @param  mixed  $types
     */
    public function tradeType($types): self
    {
        $values = array_filter(array_map('strtoupper', Arr::wrap($types)));

        if (empty($values)) {
            return $this;
        }

        return $this->whereIn('trade_type', $values);
    }

    /**
     * 按页面交易类型过滤（支付/退款）。
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
            return $this->where('trade_state', 'REFUND');
        }

        if ($values[0] === 'payment') {
            return $this->where('trade_state', 'SUCCESS');
        }

        return $this;
    }

    /**
     * 按交易状态过滤。
     *
     * @param  mixed  $states
     */
    public function tradeState($states): self
    {
        $values = array_filter(array_map('strtoupper', Arr::wrap($states)));

        if (empty($values)) {
            return $this;
        }

        return $this->whereIn('trade_state', $values);
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
     * 精确匹配微信交易号。
     *
     * EloquentFilter 会把 wechat_transaction_id 映射为 wechatTransaction。
     */
    public function wechatTransaction(string $transactionId): self
    {
        $transactionId = trim($transactionId);

        if ($transactionId === '') {
            return $this;
        }

        return $this->where('wechat_transaction_id', $transactionId);
    }

    /**
     * 兼容显式驼峰方法调用。
     */
    public function wechatTransactionId(string $transactionId): self
    {
        return $this->wechatTransaction($transactionId);
    }

    /**
     * 精确匹配商户订单号。
     */
    public function outTradeNo(string $orderNo): self
    {
        $orderNo = trim($orderNo);

        if ($orderNo === '') {
            return $this;
        }

        return $this->where('out_trade_no', $orderNo);
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

        return $this->where(function ($query) use ($keywords, $like): void {
            $query->where('wechat_transaction_id', $keywords)
                ->orWhere('out_trade_no', $keywords)
                ->orWhere('user_open_id', $keywords);

            if (config('payment-bill.wechat_keyword_search_driver', 'ilike') === 'zhparser') {
                $query->orWhereRaw(
                    "to_tsvector('payment_bill_zh', COALESCE(goods_name, '') || ' ' || COALESCE(goods_info, '')) @@ plainto_tsquery('payment_bill_zh', ?)",
                    [$keywords]
                );
            } else {
                $query->orWhere('goods_name', 'ilike', $like)
                    ->orWhere('goods_info', 'ilike', $like);
            }
        });
    }

    /**
     * 日期解析。
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
