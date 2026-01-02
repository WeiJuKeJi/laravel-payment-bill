<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use WeiJuKeJi\PaymentBill\Http\Requests\BillDownloadFilterRequest;
use WeiJuKeJi\PaymentBill\Http\Requests\BillDownloadStoreRequest;
use WeiJuKeJi\PaymentBill\Http\Resources\BillDownloadResource;
use WeiJuKeJi\PaymentBill\Models\BillDownload;
use WeiJuKeJi\PaymentBill\Services\BillDownloadService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillDownloadController extends Controller
{
    public function __construct(private readonly BillDownloadService $billDownloadService) {}

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

        return $this->respondWithResource($record, BillDownloadResource::class, 'success', 202);
    }

    public function show(BillDownload $billDownload): JsonResponse
    {
        $billDownload->loadMissing('paymentChannel');

        return $this->respondWithResource($billDownload, BillDownloadResource::class);
    }

    /**
     * 下载账单文件
     */
    public function download(BillDownload $billDownload): StreamedResponse|JsonResponse
    {
        // 检查下载状态
        if ($billDownload->download_status !== BillDownload::DOWNLOAD_STATUS_COMPLETED) {
            return $this->error('账单文件尚未下载完成，无法下载', 400);
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
        $billDownload->loadMissing('paymentChannel');
        $channel = $billDownload->paymentChannel;
        $channelName = $channel ? $channel->channel : 'unknown';
        $billDate = str_replace('-', '', $billDownload->bill_date);
        $billType = $billDownload->bill_type;

        // 从原始文件路径中提取扩展名
        $extension = pathinfo($billDownload->file_path, PATHINFO_EXTENSION);
        $filename = sprintf('%s_%s_%s.%s', $channelName, $billDate, $billType, $extension);

        // 返回文件下载响应
        return Storage::download($billDownload->file_path, $filename);
    }
}
