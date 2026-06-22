<?php

namespace WeiJuKeJi\PaymentBill\Exports;

use Illuminate\Database\Eloquent\Builder;
use WeiJuKeJi\LaravelXlswriter\Exports\QueryExport;
use WeiJuKeJi\LaravelXlswriter\Support\ExcelColumn;
use WeiJuKeJi\PaymentBill\Http\Resources\WechatBillResource;

class WechatBillExport extends QueryExport
{
    public function __construct(
        protected Builder $query,
        protected string $sheetTitle = '微信账单导出'
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function rows(): iterable
    {
        foreach ($this->query()->lazy($this->chunkSize()) as $bill) {
            yield WechatBillResource::make($bill)->resolve();
        }
    }

    public function columns(): array
    {
        return [
            ExcelColumn::make('id', 'ID')->integer()->width(10),
            ExcelColumn::make('payment_channel', '渠道名称')->valueUsing(fn (array $row) => data_get($row, 'payment_channel.name', ''))->width(18),
            ExcelColumn::make('trade_time', '交易时间')->datetime()->width(20),
            ExcelColumn::make('wechat_transaction_id', '微信订单号')->string()->width(30),
            ExcelColumn::make('out_trade_no', '商户订单号')->string()->width(30),
            ExcelColumn::make('trade_type', '交易类型')->string()->width(14),
            ExcelColumn::make('trade_state', '交易状态')->string()->width(14),
            ExcelColumn::make('payment_bank', '付款银行')->string()->width(16),
            ExcelColumn::make('currency', '货币种类')->string()->width(12),
            ExcelColumn::make('total_amount', '订单金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.total_amount', 0))->money()->width(12),
            ExcelColumn::make('settlement_amount', '应结订单金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.settlement_amount', 0))->money()->width(14),
            ExcelColumn::make('voucher_amount', '代金券金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.voucher_amount', 0))->money()->width(12),
            ExcelColumn::make('refund_amount', '退款金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.refund_amount', 0))->money()->width(12),
            ExcelColumn::make('refund_voucher_amount', '充值券退款金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.refund_voucher_amount', 0))->money()->width(16),
            ExcelColumn::make('refund_apply_amount', '申请退款金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.refund_apply_amount', 0))->money()->width(14),
            ExcelColumn::make('fee_amount', '手续费')->valueUsing(fn (array $row) => data_get($row, 'amounts.fee_amount', 0))->money()->width(12),
            ExcelColumn::make('fee_rate', '费率')->valueUsing(fn (array $row) => data_get($row, 'amounts.fee_rate', 0))->number('0.00%')->width(10),
            ExcelColumn::make('wechat_refund_no', '微信退款单号')->valueUsing(fn (array $row) => data_get($row, 'refund.wechat_refund_no', ''))->string()->width(30),
            ExcelColumn::make('out_refund_no', '商户退款单号')->valueUsing(fn (array $row) => data_get($row, 'refund.out_refund_no', ''))->string()->width(30),
            ExcelColumn::make('refund_type', '退款类型')->valueUsing(fn (array $row) => data_get($row, 'refund.refund_type', ''))->string()->width(14),
            ExcelColumn::make('refund_status', '退款状态')->valueUsing(fn (array $row) => data_get($row, 'refund.refund_status', ''))->string()->width(14),
            ExcelColumn::make('goods_name', '商品名称')->string()->width(28),
            ExcelColumn::make('goods_info', '商户数据包')->string()->width(30),
            ExcelColumn::make('reconciliation_status', '对账状态')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.status.label', ''))->width(14),
            ExcelColumn::make('business_type', '业务类型')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.business_type', ''))->string()->width(18),
            ExcelColumn::make('business_id', '业务ID')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.business_id', ''))->string()->width(18),
            ExcelColumn::make('reconciled_at', '对账时间')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.reconciled_at'))->datetime()->width(20),
            ExcelColumn::make('amount_diff', '对账差异')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.amount_diff', 0))->money()->width(12),
            ExcelColumn::make('reconciliation_remark', '对账备注')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.remark', ''))->string()->width(30),
        ];
    }
}
