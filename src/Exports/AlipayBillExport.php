<?php

namespace WeiJuKeJi\PaymentBill\Exports;

use Illuminate\Database\Eloquent\Builder;
use WeiJuKeJi\LaravelXlswriter\Exports\QueryExport;
use WeiJuKeJi\LaravelXlswriter\Support\ExcelColumn;
use WeiJuKeJi\PaymentBill\Http\Resources\AlipayBillResource;

class AlipayBillExport extends QueryExport
{
    public function __construct(
        protected Builder $query,
        protected string $sheetTitle = '支付宝账单导出'
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
            yield AlipayBillResource::make($bill)->resolve();
        }
    }

    public function columns(): array
    {
        return [
            ExcelColumn::make('id', 'ID')->integer()->width(10),
            ExcelColumn::make('payment_channel', '渠道名称')->valueUsing(fn (array $row) => data_get($row, 'payment_channel.name', ''))->width(18),
            ExcelColumn::make('bill_date', '账单日期')->date()->width(14),
            ExcelColumn::make('alipay_trade_no', '支付宝交易号')->string()->width(30),
            ExcelColumn::make('merchant_order_no', '商户订单号')->string()->width(30),
            ExcelColumn::make('biz_type', '业务类型')->string()->width(14),
            ExcelColumn::make('goods_name', '商品名称')->string()->width(28),
            ExcelColumn::make('created_time', '创建时间')->datetime()->width(20),
            ExcelColumn::make('completed_time', '完成时间')->datetime()->width(20),
            ExcelColumn::make('store_id', '门店编号')->string()->width(18),
            ExcelColumn::make('store_name', '门店名称')->string()->width(20),
            ExcelColumn::make('operator', '操作员')->string()->width(16),
            ExcelColumn::make('terminal_id', '终端号')->string()->width(18),
            ExcelColumn::make('counterparty_account', '对方账户')->string()->width(24),
            ExcelColumn::make('order_amount', '订单金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.order_amount', 0))->money()->width(12),
            ExcelColumn::make('merchant_settlement_amount', '商家实收')->valueUsing(fn (array $row) => data_get($row, 'amounts.merchant_settlement_amount', 0))->money()->width(12),
            ExcelColumn::make('alipay_red_envelope_amount', '支付宝红包')->valueUsing(fn (array $row) => data_get($row, 'amounts.alipay_red_envelope_amount', 0))->money()->width(12),
            ExcelColumn::make('point_deduction_amount', '集分宝抵扣')->valueUsing(fn (array $row) => data_get($row, 'amounts.point_deduction_amount', 0))->money()->width(12),
            ExcelColumn::make('alipay_discount_amount', '支付宝优惠')->valueUsing(fn (array $row) => data_get($row, 'amounts.alipay_discount_amount', 0))->money()->width(12),
            ExcelColumn::make('merchant_discount_amount', '商家优惠')->valueUsing(fn (array $row) => data_get($row, 'amounts.merchant_discount_amount', 0))->money()->width(12),
            ExcelColumn::make('coupon_amount', '券核销金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.coupon_amount', 0))->money()->width(12),
            ExcelColumn::make('merchant_red_envelope_amount', '商家红包')->valueUsing(fn (array $row) => data_get($row, 'amounts.merchant_red_envelope_amount', 0))->money()->width(12),
            ExcelColumn::make('card_amount', '卡消费金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.card_amount', 0))->money()->width(12),
            ExcelColumn::make('service_fee_amount', '服务费')->valueUsing(fn (array $row) => data_get($row, 'amounts.service_fee_amount', 0))->money()->width(12),
            ExcelColumn::make('profit_sharing_amount', '分账金额')->valueUsing(fn (array $row) => data_get($row, 'amounts.profit_sharing_amount', 0))->money()->width(12),
            ExcelColumn::make('coupon_name', '券名称')->string()->width(24),
            ExcelColumn::make('refund_request_no', '退款批次号')->string()->width(24),
            ExcelColumn::make('remark', '备注')->string()->width(28),
            ExcelColumn::make('reconciliation_status', '对账状态')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.status.label', ''))->width(14),
            ExcelColumn::make('business_type', '业务模型')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.business_type', ''))->string()->width(18),
            ExcelColumn::make('business_id', '业务ID')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.business_id', ''))->string()->width(18),
            ExcelColumn::make('reconciled_at', '对账时间')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.reconciled_at'))->datetime()->width(20),
            ExcelColumn::make('amount_diff', '对账差异')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.amount_diff', 0))->money()->width(12),
            ExcelColumn::make('reconciliation_remark', '对账备注')->valueUsing(fn (array $row) => data_get($row, 'reconciliation.remark', ''))->string()->width(30),
        ];
    }
}
