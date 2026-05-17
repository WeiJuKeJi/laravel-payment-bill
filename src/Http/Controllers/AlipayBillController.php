<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use WeiJuKeJi\PaymentBill\Http\Requests\AlipayBill\AlipayBillFilterRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\Reconciliation\ReconciliationRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\AlipayBillResource;
use WeiJuKeJi\PaymentBill\Models\AlipayBill;

class AlipayBillController extends Controller
{
    public function index(AlipayBillFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $order = Arr::pull($filters, 'order', 'desc');
        $withChannel = (bool) Arr::pull($filters, 'with_channel', false);
        $perPage = $this->resolvePerPage($filters, 20, 500);

        $direction = $order === 'asc' ? 'asc' : 'desc';

        $query = AlipayBill::query()->filter($filters);

        if ($withChannel) {
            $query->with('paymentChannel');
        }

        // 业务明细中 biz_type='交易' 为支付，biz_type='退款' 为退款（退款行 order_amount 为负值）。
        $summaryData = (clone $query)->selectRaw("
            COALESCE(SUM(CASE WHEN biz_type = '交易' THEN order_amount ELSE 0 END), 0) as total_payment_amount,
            COALESCE(SUM(CASE WHEN biz_type = '退款' THEN -order_amount ELSE 0 END), 0) as total_refund_amount,
            COALESCE(SUM(merchant_settlement_amount), 0) as total_settlement_amount,
            COALESCE(SUM(service_fee_amount), 0) as total_fee_amount
        ")->first();

        $summary = [
            'total_payment_amount' => number_format((float) ($summaryData->total_payment_amount ?? 0), 2, '.', ''),
            'total_refund_amount' => number_format((float) ($summaryData->total_refund_amount ?? 0), 2, '.', ''),
            'total_settlement_amount' => number_format((float) ($summaryData->total_settlement_amount ?? 0), 2, '.', ''),
            'total_fee_amount' => number_format((float) ($summaryData->total_fee_amount ?? 0), 2, '.', ''),
        ];

        $query->orderBy('completed_time', $direction)
            ->orderBy('id', $direction);

        $paginator = $query->paginate($perPage);

        return $this->respondWithPaginationAndSummary($paginator, $summary, AlipayBillResource::class);
    }

    public function show(Request $request, AlipayBill $alipayBill): JsonResponse
    {
        if ($request->boolean('with_channel')) {
            $alipayBill->loadMissing('paymentChannel');
        }

        return $this->respondWithResource($alipayBill, AlipayBillResource::class);
    }

    /**
     * 标记为已匹配
     */
    public function markAsMatched(ReconciliationRequest $request, AlipayBill $alipayBill): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['business_id'])) {
            return $this->respondWithError('业务订单ID不能为空', 422);
        }

        $alipayBill->markAsMatched(
            businessId: $data['business_id'],
            amountDiff: $data['amount_diff'] ?? 0.00,
            reconciledBy: $this->getReconciledBy($request),
            remark: $data['remark'] ?? null
        );

        return $this->respondWithResource($alipayBill->fresh(), AlipayBillResource::class);
    }

    /**
     * 标记为不匹配
     */
    public function markAsMismatched(ReconciliationRequest $request, AlipayBill $alipayBill): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['business_id'])) {
            return $this->respondWithError('业务订单ID不能为空', 422);
        }

        if (! isset($data['amount_diff'])) {
            return $this->respondWithError('金额差异不能为空', 422);
        }

        $alipayBill->markAsMismatched(
            businessId: $data['business_id'],
            amountDiff: $data['amount_diff'],
            remark: $data['remark'],
            reconciledBy: $this->getReconciledBy($request)
        );

        return $this->respondWithResource($alipayBill->fresh(), AlipayBillResource::class);
    }

    /**
     * 标记为人工处理
     */
    public function markAsManual(ReconciliationRequest $request, AlipayBill $alipayBill): JsonResponse
    {
        $data = $request->validated();

        $alipayBill->markAsManual(
            remark: $data['remark'],
            reconciledBy: $this->getReconciledBy($request),
            businessId: $data['business_id'] ?? null
        );

        return $this->respondWithResource($alipayBill->fresh(), AlipayBillResource::class);
    }

    /**
     * 标记为已忽略
     */
    public function markAsIgnored(ReconciliationRequest $request, AlipayBill $alipayBill): JsonResponse
    {
        $data = $request->validated();

        $alipayBill->markAsIgnored(
            remark: $data['remark'],
            reconciledBy: $this->getReconciledBy($request)
        );

        return $this->respondWithResource($alipayBill->fresh(), AlipayBillResource::class);
    }

    /**
     * 重置为待对账
     */
    public function markAsPending(AlipayBill $alipayBill): JsonResponse
    {
        $alipayBill->markAsPending();

        return $this->respondWithResource($alipayBill->fresh(), AlipayBillResource::class);
    }

    /**
     * 获取对账操作人标识
     */
    protected function getReconciledBy(Request $request): string
    {
        $user = $request->user();

        if ($user && method_exists($user, 'getAuthIdentifier')) {
            return 'user_'.$user->getAuthIdentifier();
        }

        return 'unknown';
    }
}
