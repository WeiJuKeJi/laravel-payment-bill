<?php

namespace WeiJuKeJi\PaymentBill\ModelFilters;

use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;

class PaymentChannelFilter extends ModelFilter
{
    protected $blacklist = ['page', 'per_page'];

    /**
     * 按渠道筛选
     */
    public function channel(string|array $channels): self
    {
        $values = array_filter(Arr::wrap($channels));

        if (empty($values)) {
            return $this;
        }

        return $this->whereIn('channel', $values);
    }

    /**
     * 按模式筛选
     */
    public function mode(string|array $modes): self
    {
        $values = array_filter(Arr::wrap($modes));

        if (empty($values)) {
            return $this;
        }

        return $this->whereIn('mode', $values);
    }

    /**
     * 按启用状态筛选
     */
    public function isEnabled(bool|string|int $value): self
    {
        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return $this->where('is_enabled', $enabled);
    }

    /**
     * 关键词搜索
     */
    public function keywords(string $keywords): self
    {
        $keywords = trim($keywords);

        if ($keywords === '') {
            return $this;
        }

        $like = sprintf('%%%s%%', $keywords);

        return $this->where(function ($query) use ($like) {
            $query->where('name', 'ilike', $like)
                ->orWhere('wechat_mch_id', 'ilike', $like)
                ->orWhere('wechat_sub_mch_id', 'ilike', $like)
                ->orWhere('alipay_app_id', 'ilike', $like);
        });
    }
}
