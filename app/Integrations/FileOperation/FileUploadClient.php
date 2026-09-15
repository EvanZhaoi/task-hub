<?php

namespace App\Integrations\FileOperation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 总部文件上传接口客户端。
 *
 * React 只把文件传给 TaskHub 后端。
 * 本类由 Laravel 后端携带当前登录人的 accessToken 调用总部上传接口，避免 token 暴露到前端。
 */
class FileUploadClient
{
    /**
     * 上传单个文件到总部文件服务。
     *
     * 当前 MVP 不做分片上传、进度条、预览和远程删除。
     */
    public function upload(UploadedFile $file, string $accessToken): UploadedFileRef
    {
        $baseUrl = $this->configuredBaseUrl();
        $uploadPath = $this->configuredUploadPath();
        $serviceKey = $this->configuredServiceKey();

        try {
            Log::info('File upload request started.', [
                // 只记录排查需要的非敏感信息，不记录 accessToken。
                'base_url' => $baseUrl,
                'path' => $uploadPath,
                'service_key_present' => $serviceKey !== '',
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);

            $request = Http::baseUrl($baseUrl)
                ->timeout((int) config('file_operation.timeout', 10))
                ->acceptJson()
                // 总部接口调用统一使用当前登录人的动态 accessToken。
                ->withHeaders(['Authorization' => 'bearer '.$accessToken])
                // attach() 会把请求转换为 multipart/form-data，并把 file 作为真实文件字段提交。
                ->attach(
                    name: 'file',
                    contents: fopen($file->getRealPath(), 'r'),
                    filename: $file->getClientOriginalName(),
                );

            if (! config('file_operation.verify_ssl')) {
                // 内网测试环境可能暂时没有完整证书链；生产环境应开启 SSL 校验。
                $request = $request->withoutVerifying();
            }

            $response = $request->post($uploadPath, [
                // serviceKey 必填，但必须来自后端配置，不能写死在 React 或 Controller。
                'serviceKey' => $serviceKey,
                // businessKey/modelKey 当前没有业务需求，按要求不传。
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('File upload request connection failed.', [
                'base_url' => $baseUrl,
                'path' => $uploadPath,
                'message' => $exception->getMessage(),
            ]);

            throw new FileUploadException('Unable to connect to file upload service.', previous: $exception);
        }

        if (! $response->successful()) {
            Log::warning('File upload request returned an unsuccessful status.', [
                'base_url' => $baseUrl,
                'path' => $uploadPath,
                'status' => $response->status(),
                // 只截取响应预览，避免日志过大；这里不包含 accessToken。
                'body_preview' => mb_substr($response->body(), 0, 1000),
            ]);

            throw new FileUploadException(sprintf(
                'File upload request failed with HTTP status %d.',
                $response->status(),
            ));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            Log::warning('File upload response is not JSON.', [
                'base_url' => $baseUrl,
                'path' => $uploadPath,
                'body_preview' => mb_substr($response->body(), 0, 1000),
            ]);

            throw new FileUploadException('File upload response is not a JSON object.');
        }

        $uploadedFile = UploadedFileRef::fromPayload($payload);

        Log::info('File upload request succeeded.', [
            'base_url' => $baseUrl,
            'path' => $uploadPath,
            // 只记录总部返回的附件 ID 和文件名，便于排查发布任务附件关联问题。
            'attachment_id' => $uploadedFile->id(),
            'file_name' => $uploadedFile->name(),
        ]);

        return $uploadedFile;
    }

    /**
     * 读取并校验总部文件服务基础地址。
     */
    private function configuredBaseUrl(): string
    {
        $baseUrl = config('file_operation.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new FileUploadException('File operation base URL is not configured.');
        }

        return $baseUrl;
    }

    /**
     * 读取并校验总部文件上传接口 path。
     */
    private function configuredUploadPath(): string
    {
        $uploadPath = config('file_operation.upload_path');

        if (! is_string($uploadPath) || $uploadPath === '') {
            throw new FileUploadException('File upload path is not configured.');
        }

        if ($this->isAbsoluteUrl($uploadPath)) {
            throw new FileUploadException('File upload path must be a path, not a full URL.');
        }

        return $uploadPath;
    }

    /**
     * 读取并校验总部文件服务 serviceKey。
     */
    private function configuredServiceKey(): string
    {
        $serviceKey = config('file_operation.service_key');

        if (! is_string($serviceKey) || $serviceKey === '') {
            throw new FileUploadException('File operation service key is not configured.');
        }

        return $serviceKey;
    }

    /**
     * 判断配置值是否是完整 URL。
     *
     * path 配置只允许写路径，完整域名统一放在 base_url。
     */
    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}
