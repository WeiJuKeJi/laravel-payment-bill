<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use WeiJuKeJi\PaymentBill\Http\Requests\ReconciliationRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\WechatBillFilterRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\WechatBillResource;
use WeiJuKeJi\PaymentBill\Models\WechatBill;

class WechatBillController extends Controller
{
    public function index(WechatBillFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $order = Arr::pull($filters, 'order', 'desc');
        $withChannel = (bool) Arr::pull($filters, 'with_channel', false);
        $perPage = $this->resolvePerPage($filters, 20, 500);

        $direction = $order === 'asc' ? 'asc' : 'desc';

        $query = WechatBill::query()->filter($filters);

        if ($withChannel) {
            $query->with('paymentChannel');
        }

        $query->orderBy('trade_time', $direction)
            ->orderBy('id', $direction);

        $paginator = $query->paginate($perPage);

        return $this->respondWithPagination($paginator, WechatBillResource::class);
    }

    public function show(Request $request, WechatBill $wechatBill): JsonResponse
    {
        if ($request->boolean('with_channel')) {
            $wechatBill->loadMissing('paymentChannel');
        }

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
            businessId: $data['business_id'],
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
            businessId: $data['business_id'],
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
            businessId: $data['business_id'] ?? null
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
