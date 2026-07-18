<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use WeiJuKeJi\LaravelXlswriter\Facades\Xlswriter;
use WeiJuKeJi\PaymentBill\Exports\WechatBillExport;
use WeiJuKeJi\PaymentBill\Http\Requests\Reconciliation\ReconciliationRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\WechatBill\WechatBillFilterRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\WechatBillResource;
use WeiJuKeJi\PaymentBill\Models\WechatBill;
use WeiJuKeJi\PaymentBill\Support\ResolvesBillProjects;

class WechatBillController extends Controller
{
    use ResolvesBillProjects;

    public function index(WechatBillFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $order = Arr::pull($filters, 'order', 'desc');
        $withChannel = (bool) Arr::pull($filters, 'with_channel', false);
        $projectId = Arr::pull($filters, 'project_id');
        $hasProject = Arr::pull($filters, 'has_project');
        $perPage = $this->resolvePerPage($filters, 20, 500);

        $direction = $order === 'asc' ? 'asc' : 'desc';

        $query = WechatBill::query();
        $this->applyResolvedProjectFilters($query, $projectId, $hasProject);
        $query->filter($filters);

        if ($withChannel) {
            $query->with('paymentChannel');
        }

        $summaryData = (clone $query)->selectRaw('
            COALESCE(SUM(total_amount), 0) as total_payment_amount,
            COALESCE(SUM(refund_amount), 0) as total_refund_amount,
            COALESCE(SUM(fee_amount), 0) as total_fee_amount
        ')->first();

        $totalPaymentAmount = (float) ($summaryData->total_payment_amount ?? 0);
        $totalRefundAmount = (float) ($summaryData->total_refund_amount ?? 0);
        $totalSettlementAmount = $totalPaymentAmount - $totalRefundAmount;

        $summary = [
            'total_payment_amount' => number_format($totalPaymentAmount, 2, '.', ''),
            'total_refund_amount' => number_format($totalRefundAmount, 2, '.', ''),
            'total_settlement_amount' => number_format($totalSettlementAmount, 2, '.', ''),
            'total_fee_amount' => number_format((float) ($summaryData->total_fee_amount ?? 0), 2, '.', ''),
        ];

        $query->orderBy('trade_time', $direction)
            ->orderBy('id', $direction);

        $paginator = $query->paginate($perPage);
        $this->attachResolvedProjects($paginator->getCollection());

        return $this->respondWithPaginationAndSummary($paginator, $summary, WechatBillResource::class);
    }

    public function export(WechatBillFilterRequest $request): BinaryFileResponse
    {
        $filters = $request->validated();
        $order = Arr::pull($filters, 'order', 'desc');
        Arr::forget($filters, ['per_page', 'with_channel']);

        $direction = $order === 'asc' ? 'asc' : 'desc';

        $query = WechatBill::query()
            ->filter($filters)
            ->with('paymentChannel')
            ->orderBy('trade_time', $direction)
            ->orderBy('id', $direction);

        $filename = sprintf('payment-bill-wechat-bills-%s.xlsx', now()->format('YmdHis'));
        $path = storage_path('app/tmp/payment-bill-exports/'.$filename);

        Xlswriter::export(new WechatBillExport($query))->toFile($path, $filename);

        return response()
            ->download($path, $filename)
            ->deleteFileAfterSend(true);
    }

    public function show(Request $request, WechatBill $wechatBill): JsonResponse
    {
        if ($request->boolean('with_channel')) {
            $wechatBill->loadMissing('paymentChannel');
        }

        $this->attachResolvedProjects(collect([$wechatBill]));

        return $this->respondWithResource($wechatBill, WechatBillResource::class);
    }

    /**
     * 标记为已匹配
     */
    public function markAsMatched(ReconciliationRequest $request, WechatBill $wechatBill): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['business_id'])) {
            return $this->respondWithError('业务订单ID不能为空', 422);
        }

        $wechatBill->markAsMatched(
            business: $data['business_id'],
            amountDiff: $data['amount_diff'] ?? 0.00,
            reconciledBy: $this->getReconciledBy($request),
            remark: $data['remark'] ?? null
        );

        return $this->respondWithResource($wechatBill->fresh(), WechatBillResource::class);
    }

    /**
     * 标记为不匹配
     */
    public function markAsMismatched(ReconciliationRequest $request, WechatBill $wechatBill): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['business_id'])) {
            return $this->respondWithError('业务订单ID不能为空', 422);
        }

        if (! isset($data['amount_diff'])) {
            return $this->respondWithError('金额差异不能为空', 422);
        }

        $wechatBill->markAsMismatched(
            business: $data['business_id'],
            amountDiff: $data['amount_diff'],
            remark: $data['remark'],
            reconciledBy: $this->getReconciledBy($request)
        );

        return $this->respondWithResource($wechatBill->fresh(), WechatBillResource::class);
    }

    /**
     * 标记为人工处理
     */
    public function markAsManual(ReconciliationRequest $request, WechatBill $wechatBill): JsonResponse
    {
        $data = $request->validated();

        $wechatBill->markAsManual(
            remark: $data['remark'],
            reconciledBy: $this->getReconciledBy($request),
            business: $data['business_id'] ?? null
        );

        return $this->respondWithResource($wechatBill->fresh(), WechatBillResource::class);
    }

    /**
     * 标记为已忽略
     */
    public function markAsIgnored(ReconciliationRequest $request, WechatBill $wechatBill): JsonResponse
    {
        $data = $request->validated();

        $wechatBill->markAsIgnored(
            remark: $data['remark'],
            reconciledBy: $this->getReconciledBy($request)
        );

        return $this->respondWithResource($wechatBill->fresh(), WechatBillResource::class);
    }

    /**
     * 重置为待对账
     */
    public function markAsPending(WechatBill $wechatBill): JsonResponse
    {
        $wechatBill->markAsPending();

        return $this->respondWithResource($wechatBill->fresh(), WechatBillResource::class);
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
