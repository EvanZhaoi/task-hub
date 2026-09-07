<?php

namespace App\Integrations\Personnel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 本据点全员人员接口客户端。
 *
 * SSO 当前登录人接口只负责证明“这个人是谁”，但字段可能不够完整。
 * 本客户端专门读取本据点人员列表，用于登录 Session 信息增强和未来开发者选择器。
 */
class PersonnelClient
{
    /**
     * 获取本据点全部人员列表。
     *
     * 优先读取 Redis 缓存；缓存不存在时访问外部接口，并在成功后写入缓存。
     *
     * @return list<PersonnelUser>
     */
    public function fetchAll(?string $accessToken = null): array
    {
        if ($users = $this->cachedUsers()) {
            return $users;
        }

        $users = $this->fetchAllFromRemote($accessToken);
        $this->putCacheIfNotEmpty($users);

        return $users;
    }

    /**
     * 刷新本据点人员缓存。
     *
     * 该方法供定时任务调用；如果外部接口返回空列表，不覆盖旧缓存。
     */
    public function refreshCache(?string $accessToken = null): int
    {
        // 定时任务调用该方法刷新 Redis。
        // 如果外部接口失败或返回空列表，不覆盖旧缓存，避免登录和人员选择器突然失去数据。
        $users = $this->fetchAllFromRemote($accessToken ?? $this->configuredAccessToken());

        if ($users === []) {
            return 0;
        }

        $this->putCacheIfNotEmpty($users);

        return count($users);
    }

    /**
     * 从外部接口实时获取本据点人员列表。
     *
     * 该方法绕过缓存，专门给缓存刷新流程使用。
     *
     * @return list<PersonnelUser>
     */
    private function fetchAllFromRemote(?string $accessToken): array
    {
        $baseUrl = config('personnel.base_url');
        $path = config('personnel.list_path');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new PersonnelException('Personnel base URL is not configured.');
        }

        if (! is_string($path) || $path === '') {
            throw new PersonnelException('Personnel list path is not configured.');
        }

        if ($this->isAbsoluteUrl($path)) {
            throw new PersonnelException('Personnel list path must be a path, not a full URL.');
        }

        $method = strtoupper((string) config('personnel.method', 'GET'));
        $token = $this->normalizeAccessToken($accessToken);

        try {
            $request = Http::baseUrl($baseUrl)
                ->timeout((int) config('personnel.timeout', 3))
                ->acceptJson()
                // 本据点人员列表接口已切换为 token 调用方式，所有真实请求都必须带 Authorization Header。
                ->withHeaders(['Authorization' => 'bearer '.$token]);

            if (! config('personnel.verify_ssl')) {
                // 内网测试环境可能暂时没有完整证书链；生产环境应开启 SSL 校验。
                $request = $request->withoutVerifying();
            }

            $response = match ($method) {
                'POST' => $request->asJson()->post($path),
                'GET' => $request->get($path),
                default => throw new PersonnelException('Unsupported personnel HTTP method.'),
            };
        } catch (ConnectionException $exception) {
            throw new PersonnelException('Unable to connect to personnel service.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new PersonnelException(sprintf(
                'Personnel request failed with HTTP status %d.',
                $response->status(),
            ));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new PersonnelException('Personnel response is not a JSON object.');
        }

        return $this->usersFromPayload($payload);
    }

    /**
     * 根据工号查找本据点人员。
     *
     * 登录成功后用它判断当前人是否属于本据点，并补充更准确的 siteUser 信息。
     */
    public function findByEmployeeNo(string $employeeNo, ?string $accessToken = null): ?PersonnelUser
    {
        $normalizedEmployeeNo = PersonnelUser::normalizeEmployeeNo($employeeNo);

        if ($normalizedEmployeeNo === null) {
            return null;
        }

        foreach ($this->fetchAll($accessToken) as $user) {
            if ($user->employeeNo() === $normalizedEmployeeNo) {
                return $user;
            }
        }

        return null;
    }

    /**
     * 把外部人员列表响应转换为 PersonnelUser 对象列表。
     *
     * 这里直接把外部 API 完整响应交给 PersonnelUser::listFromPayload()。
     * 人员接口没有“单个人员详情”返回，公开解析入口必须基于完整列表响应。
     *
     * @return list<PersonnelUser>
     */
    private function usersFromPayload(array $payload): array
    {
        return PersonnelUser::listFromPayload($payload);
    }

    /**
     * 判断配置值是否是完整 URL。
     *
     * list_path 只允许配置路径，域名统一从 personnel.base_url 读取。
     */
    private function isAbsoluteUrl(string $value): bool
    {
        // list_path 必须是 path，防止 base_url + full_url 拼出错误请求地址。
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    /**
     * 从缓存读取本据点人员列表。
     *
     * 缓存不可用时记录 warning 并返回空数组，调用方会退回外部接口。
     *
     * @return list<PersonnelUser>
     */
    private function cachedUsers(): array
    {
        try {
            $cached = Cache::store((string) config('personnel.cache_store', 'redis'))
                ->get((string) config('personnel.cache_key', 'taskhub:personnel_users'));
        } catch (Throwable $exception) {
            Log::warning('Unable to read personnel cache.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! is_array($cached)) {
            return [];
        }

        return $this->usersFromPayload($cached);
    }

    /**
     * 将非空人员列表写入缓存。
     *
     * 空列表不写入缓存，避免接口异常时清空上一次成功同步的数据。
     *
     * @param  list<PersonnelUser>  $users
     */
    private function putCacheIfNotEmpty(array $users): void
    {
        if ($users === []) {
            return;
        }

        try {
            Cache::store((string) config('personnel.cache_store', 'redis'))
                ->put(
                    (string) config('personnel.cache_key', 'taskhub:personnel_users'),
                    array_map(fn (PersonnelUser $user): array => $user->toCachePayload(), $users),
                    (int) config('personnel.cache_ttl', 86400),
                );
        } catch (Throwable $exception) {
            Log::warning('Unable to write personnel cache.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 读取定时刷新使用的人员列表接口 token。
     *
     * 登录请求会把 SSO accessToken 传进来；定时命令没有当前用户，只能使用服务端配置。
     */
    private function configuredAccessToken(): ?string
    {
        $token = config('personnel.access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * 校验外部接口 token 是否可用。
     *
     * token 缺失时直接抛异常，避免发出无 Authorization Header 的无效请求。
     */
    private function normalizeAccessToken(?string $accessToken): string
    {
        if (is_string($accessToken) && $accessToken !== '') {
            return $accessToken;
        }

        throw new PersonnelException('Personnel access token is not configured.');
    }
}
