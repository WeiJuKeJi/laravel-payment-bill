<?php

namespace WeiJuKeJi\PaymentBill\ModelFilters;

use Carbon\Carbon;
use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;

/**
 * payment_bill_downloads 表的筛选器，统一 REST 查询体验。
 */
class BillDownloadFilter extends ModelFilter
{
    /**
     * 需要忽略的公共查询参数。
     *
     * @var array<int, string>
     */
    protected $blacklist = ['page', 'per_page'];

    /**
     * 按支付渠道筛选，支持数组/逗号分隔。
     *
     * @param  mixed  $channelIds
     */
    public function paymentChannel($channelIds): self
    {
        return $this->paymentChannelId($channelIds);
    }

    /**
     * 按支付渠道筛选，支持数组/逗号分隔。
     *
     * @param  mixed  $channelIds
     */
    public function paymentChannelId($channelIds): self
    {
        $ids = array_filter(Arr::wrap($channelIds));

        if (empty($ids)) {
            return $this;
        }

        return $this->whereIn('payment_channel_id', $ids);
    }

    /**
     * 按账单状态筛选。
     *
     * @param  mixed  $statuses
     */
    public function downloadStatus($statuses): self
    {
        $values = array_filter(Arr::wrap($statuses));

        if (empty($values)) {
            return $this;
        }

        return $this->whereIn('download_status', $values);
    }

    /**
     * 按导入状态筛选。
     *
     * @param  mixed  $statuses
     */
    public function importStatus($statuses): self
    {
        $values = array_filter(Arr::wrap($statuses));

        if (empty($values)) {
            return $this;
        }

        return $this->whereIn('import_status', $values);
    }

    /**
     * 按账单类型筛选。
     *
     * @param  mixed  $billTypes
     */
    public function billType($billTypes): self
    {
        $types = array_filter(array_map('strtoupper', Arr::wrap($billTypes)));

        if (empty($types)) {
            return $this;
        }

        return $this->whereIn('bill_type', $types);
    }

    /**
     * 筛选指定日期的账单。
     */
    public function billDate(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (!$parsed) {
            return $this;
        }

        return $this->whereDate('bill_date', $parsed->toDateString());
    }

    /**
     * 筛选账单日期在指定范围内的记录。
     */
    public function dateBetween($range): self
    {
        $dates = Arr::wrap($range);

        if (count($dates) < 2) {
            return $this;
        }

        $from = $this->parseDate($dates[0]);
        $to = $this->parseDate($dates[1]);

        if (!$from || !$to) {
            return $this;
        }

        return $this->whereBetween('bill_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * 指定开始日期（含）。
     */
    public function dateFrom(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (!$parsed) {
            return $this;
        }

        return $this->whereDate('bill_date', '>=', $parsed->toDateString());
    }

    /**
     * 指定结束日期（含）。
     */
    public function dateTo(string $date): self
    {
        $parsed = $this->parseDate($date);

        if (!$parsed) {
            return $this;
        }

        return $this->whereDate('bill_date', '<=', $parsed->toDateString());
    }

    /**
     * 模糊搜索失败原因或文件路径。
     */
    public function keywords(string $keywords): self
    {
        $keywords = trim($keywords);

        if ($keywords === '') {
            return $this;
        }

        $like = sprintf('%%%s%%', $keywords);

        return $this->where(function ($query) use ($like) {
            $query->where('download_error', 'ilike', $like)
                ->orWhere('file_path', 'ilike', $like);
        });
    }

    /**
     * 解析日期，异常时返回 null。
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
