<?php

namespace App\Integrations\FileOperation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
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
        $timeout = (int) config('file_operation.timeout', 10);
        $connectTimeout = (int) config('file_operation.connect_timeout', 10);
        $startedAt = hrtime(true);
        $stream = $this->openFileStream($file);
        $diagnostics = [
            // 诊断日志不记录 accessToken、serviceKey 内容或文件内容。
            'base_url' => $baseUrl,
            'path' => $uploadPath,
            'service_key_present' => $serviceKey !== '',
            'file_name' => $file->getClientOriginalName(),
            'file_size_bytes' => $file->getSize(),
            'timeout_seconds' => $timeout,
            'connect_timeout_seconds' => $connectTimeout,
            'php_max_execution_time_seconds' => (int) ini_get('max_execution_time'),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            // Guzzle 可能读取系统代理环境变量；只记录是否配置，不记录可能含凭据的代理地址。
            'proxy_environment_configured' => $this->hasProxyEnvironment(),
        ];

        try {
            Log::info('File upload request started.', $diagnostics);

            $request = Http::baseUrl($baseUrl)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                // Guzzle 默认在请求体达到 1 MiB 时加入 Expect: 100-Continue。
                // 总部网关未正确完成该握手时会只让大文件等待，因此本上传接口直接发送请求体。
                ->withOptions(['expect' => false])
                ->acceptJson()
                // 总部接口调用统一使用当前登录人的动态 accessToken。
                ->withHeaders(['Authorization' => 'bearer '.$accessToken])
                // 记录 Guzzle 准备完成后的请求元数据，用于确认 Content-Length、分块传输和 Expect 行为。
                ->beforeSending(function (ClientRequest $request, array $options) use ($diagnostics): void {
                    $psrRequest = $request->toPsrRequest();

                    Log::debug('File upload HTTP request prepared.', [
                        ...$diagnostics,
                        'request_body_size_bytes' => $psrRequest->getBody()->getSize(),
                        'content_length' => $psrRequest->getHeaderLine('Content-Length') ?: null,
                        'transfer_encoding' => $psrRequest->getHeaderLine('Transfer-Encoding') ?: null,
                        'expect_header' => $psrRequest->getHeaderLine('Expect') ?: null,
                        'expect_option' => $options['expect'] ?? null,
                    ]);
                })
                // attach() 会把请求转换为 multipart/form-data，并把 file 作为真实文件字段提交。
                ->attach(
                    name: 'file',
                    // 使用可 seek 的二进制文件流，让 Guzzle 正确计算整个 multipart 请求的 Content-Length。
                    contents: $stream,
                    filename: $file->getClientOriginalName(),
                );

            if (! config('file_operation.verify_ssl')) {
                // 内网测试环境可能暂时没有完整证书链；生产环境应开启 SSL 校验。
                $request = $request->withoutVerifying();
            }

            $response = $request->post($uploadPath, [
                // serviceKey 必填，但必须来自后端配置，不能写死在 React 或 Controller。
                'serviceKey' => $serviceKey,
                // 总部上传接口 4 个 RequestParam 都要求提交；当前业务还没有关联规则，先传空字符串。
                'businessKey' => '',
                'modelKey' => '',
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('File upload request connection failed.', [
                ...$diagnostics,
                'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
                'message' => $exception->getMessage(),
            ]);

            throw new FileUploadException('无法连接文件上传接口。', previous: $exception);
        } finally {
            // PendingRequest::post() 是同步调用；请求结束后立即关闭本地临时文件流，避免句柄泄漏。
            fclose($stream);
        }

        if (! $response->successful()) {
            Log::warning('File upload request returned an unsuccessful status.', [
                ...$diagnostics,
                'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
                'status' => $response->status(),
                // 只截取响应预览，避免日志过大；这里不包含 accessToken。
                'body_preview' => mb_substr($response->body(), 0, 1000),
            ]);

            throw new FileUploadException(sprintf(
                '文件上传接口请求失败，HTTP 状态码：%d。',
                $response->status(),
            ));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            Log::warning('File upload response is not JSON.', [
                ...$diagnostics,
                'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
                'body_preview' => mb_substr($response->body(), 0, 1000),
            ]);

            throw new FileUploadException('文件上传接口返回的不是有效 JSON 对象。');
        }

        $uploadedFile = UploadedFileRef::fromPayload($payload);

        Log::info('File upload request succeeded.', [
            ...$diagnostics,
            'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
            'status' => $response->status(),
            // 只记录总部返回的附件 ID 和文件名，便于排查发布任务附件关联问题。
            'attachment_id' => $uploadedFile->id(),
            'file_name' => $uploadedFile->name(),
        ]);

        return $uploadedFile;
    }

    /**
     * 以二进制只读模式打开 Laravel 接收的临时上传文件。
     *
     * 返回 resource 而不是一次性读取为字符串，可以避免大文件在 TaskHub 中被完整复制到 PHP 内存。
     *
     * @return resource
     */
    private function openFileStream(UploadedFile $file)
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new FileUploadException('无法读取待上传的临时文件。');
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new FileUploadException('无法打开待上传的临时文件。');
        }

        return $stream;
    }

    /**
     * 判断当前 PHP 进程是否配置了 HTTP 代理环境变量。
     *
     * 这里只用于诊断，不读取或记录代理地址，避免代理凭据进入日志。
     */
    private function hasProxyEnvironment(): bool
    {
        foreach (['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy'] as $name) {
            $value = getenv($name);

            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算上传调用从开始到当前时刻的耗时，单位为毫秒。
     */
    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 2);
    }

    /**
     * 读取并校验总部文件服务基础地址。
     */
    private function configuredBaseUrl(): string
    {
        $baseUrl = config('file_operation.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new FileUploadException('文件服务基础地址未配置。');
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
            throw new FileUploadException('文件上传接口路径未配置。');
        }

        if ($this->isAbsoluteUrl($uploadPath)) {
            throw new FileUploadException('文件上传接口路径只能填写 path，不能填写完整 URL。');
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
            throw new FileUploadException('文件服务 serviceKey 未配置。');
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
