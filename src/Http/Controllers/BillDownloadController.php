<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use WeiJuKeJi\PaymentBill\Http\Requests\BillDownload\BillDownloadFilterRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\BillDownload\BillDownloadStoreRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\BillDownloadResource;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Services\BillDownloadService;
use WeiJuKeJi\PaymentBill\Services\BillImportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillDownloadController extends Controller
{
    public function __construct(
        private readonly BillDownloadService $billDownloadService,
        private readonly BillImportService $billImportService
    ) {}

    /**
     * 列出账单下载记录，围绕标准过滤器提供统一查询能力。
     */
    public function index(BillDownloadFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $order = Arr::pull($filters, 'order', 'desc');
        $onlyFailed = (bool) Arr::pull($filters, 'only_failed', false);
        $perPage = $this->resolvePerPage($filters, 15, 500);

        $order = $order === 'asc' ? 'asc' : 'desc';

        $query = BillDownload::with('paymentChannel')
            ->filter($filters)
            ->orderBy('bill_date', $order)
            ->orderBy('id', $order === 'asc' ? 'asc' : 'desc');

        if ($onlyFailed) {
            $query->where('download_status', BillDownload::DOWNLOAD_STATUS_FAILED);
        }

        $paginator = $query->paginate($perPage);

        return $this->respondWithPagination($paginator, BillDownloadResource::class);
    }

    public function store(BillDownloadStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $billType = strtoupper($data['bill_type'] ?? 'ALL');

        $options = array_filter([
            'force' => $data['force'] ?? false,
            'tar_type' => $data['tar_type'] ?? null,
        ], static fn ($value) => $value !== null && $value !== false);

        $record = $this->billDownloadService->download(
            $data['payment_channel_id'],
            $data['bill_date'],
            $billType,
            $options
        );

        return $this->respondWithResource($record, BillDownloadResource::class, 'created', 200);
    }

    public function show(BillDownload $billDownload): JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        return $this->respondWithResource($billDownload, BillDownloadResource::class);
    }

    /**
     * 下载账单文件到浏览器
     */
    public function downloadFile(BillDownload $billDownload): StreamedResponse|JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        // 检查下载状态
        if ($billDownload->download_status !== BillDownload::DOWNLOAD_STATUS_COMPLETED) {
            return $this->error('账单文件尚未下载完成', 400);
        }

        // 检查文件路径
        if (! $billDownload->file_path) {
            return $this->error('账单文件路径不存在', 404);
        }

        // 检查文件是否存在
        if (! Storage::exists($billDownload->file_path)) {
            return $this->error('账单文件不存在', 404);
        }

        // 生成文件名
        $channel = $billDownload->paymentChannel;
        $channelName = $channel ? $channel->channel : 'unknown';
        $billDate = str_replace('-', '', $billDownload->bill_date);
        $billType = $billDownload->bill_type;
        $extension = pathinfo($billDownload->file_path, PATHINFO_EXTENSION);
        $filename = sprintf('%s_%s_%s.%s', $channelName, $billDate, $billType, $extension);

        return Storage::download($billDownload->file_path, $filename);
    }

    /**
     * 重新下载账单
     *
     * 从支付平台重新下载该账单文件到服务器。
     */
    public function download(BillDownload $billDownload): JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        // 检查支付渠道
        if (! $billDownload->paymentChannel) {
            return $this->error('账单缺少关联的支付渠道信息', 400);
        }

        try {
            // 强制重新下载
            $record = $this->billDownloadService->download(
                $billDownload->payment_channel_id,
                $billDownload->bill_date,
                $billDownload->bill_type,
                ['force' => true]
            );

            return $this->respondWithResource($record, BillDownloadResource::class, '账单下载任务已派发');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->error('下载任务派发失败：'.$e->getMessage(), 500);
        }
    }

    /**
     * 重新导入账单数据
     *
     * 将账单文件数据重新解析并导入到账单明细表。
     */
    public function import(BillDownload $billDownload): JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        // 检查下载状态
        if ($billDownload->download_status !== BillDownload::DOWNLOAD_STATUS_COMPLETED) {
            return $this->error('账单文件尚未下载完成', 400);
        }

        // 检查文件路径
        if (! $billDownload->file_path) {
            return $this->error('账单文件路径不存在', 400);
        }

        // 检查文件是否存在
        $disk = config('payment-bill.storage_disk');
        if (! Storage::disk($disk)->exists($billDownload->file_path)) {
            return $this->error('账单文件不存在或已被删除', 404);
        }

        // 检查支付渠道
        if (! $billDownload->paymentChannel) {
            return $this->error('账单缺少关联的支付渠道信息', 400);
        }

        $channel = $billDownload->paymentChannel->channel;

        try {
            // 根据渠道类型派发导入任务（强制重新导入）
            if ($channel === 'wechat') {
                $this->billImportService->dispatchWechatImport($billDownload, force: true);
            } elseif ($channel === 'alipay') {
                $this->billImportService->dispatchAlipayImport($billDownload, force: true);
            } else {
                return $this->error("不支持的支付渠道类型：{$channel}", 400);
            }

            // 更新导入状态为待导入
            $billDownload->update([
                'import_status' => BillDownload::IMPORT_STATUS_PENDING,
                'imported_at' => null,
            ]);

            // 重新加载模型以获取最新数据
            $billDownload->refresh();

            return $this->respondWithResource(
                $billDownload,
                BillDownloadResource::class,
                $channel === 'wechat' ? '微信账单导入任务已派发' : '支付宝账单导入任务已派发'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->error('导入任务派发失败：'.$e->getMessage(), 500);
        }
    }
}
