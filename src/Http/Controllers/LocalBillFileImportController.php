<?php

namespace WeiJuKeJi\PaymentBill\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WeiJuKeJi\PaymentBill\Http\Requests\LocalBillFileImport\LocalBillFileImportRequest;
use WeiJuKeJi\PaymentBill\Services\LocalBillFileImportService;

/**
 * 本地账单文件导入控制器。
 *
 * 提供历史账单文件的 Web 上传导入功能。
 */
class LocalBillFileImportController extends Controller
{
    public function __construct(private readonly LocalBillFileImportService $importService) {}

    /**
     * 批量导入本地账单文件。
     *
     * 接收上传的账单文件（CSV 格式），解析文件名中的日期，
     * 创建或更新 BillDownload 记录，并可选择性触发账单数据导入任务。
     *
     * @param  LocalBillFileImportRequest  $request
     * @return JsonResponse
     */
    public function import(LocalBillFileImportRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->importService->import(
                files: $data['files'],
                paymentChannelId: $data['payment_channel_id'],
                billType: strtoupper($data['bill_type'] ?? 'ALL'),
                force: (bool) ($data['force'] ?? false),
                autoImport: (bool) ($data['auto_import'] ?? false),
                billPeriod: $data['bill_period'] ?? 'day'
            );

            // 判断是否有失败的文件
            if ($result['failed'] > 0) {
                return $this->success(
                    data: $result,
                    msg: '部分文件导入失败，请检查详情',
                    code: 207 // Multi-Status
                );
            }

            return $this->success(
                data: $result,
                msg: '导入完成'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error(
                msg: $e->getMessage(),
                code: 400
            );
        } catch (\Throwable $e) {
            return $this->error(
                msg: '导入失败：'.$e->getMessage(),
                code: 500
            );
        }
    }
}
