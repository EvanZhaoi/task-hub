<?php

namespace App\Integrations\Personnel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * 本据点全员人员接口客户端。
 *
 * SSO 当前登录人接口只负责证明“这个人是谁”，但字段可能不够完整。
 * 本客户端专门读取本据点人员列表，用于登录 Session 信息增强和未来开发者选择器。
 */
class PersonnelClient
{
    /**
     * @return list<PersonnelUser>
     */
    public function fetchAll(): array
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

        try {
            $request = Http::baseUrl($baseUrl)
                ->timeout((int) config('personnel.timeout', 3))
                ->acceptJson();

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

    public function findByEmployeeNo(string $employeeNo): ?PersonnelUser
    {
        $normalizedEmployeeNo = PersonnelUser::normalizeEmployeeNo($employeeNo);

        if ($normalizedEmployeeNo === null) {
            return null;
        }

        foreach ($this->fetchAll() as $user) {
            if ($user->employeeNo() === $normalizedEmployeeNo) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return list<PersonnelUser>
     */
    private function usersFromPayload(array $payload): array
    {
        // 外部列表接口常见返回形式可能是：
        // 1. 直接返回人员数组：[{"employeeNo": "..."}]
        // 2. 包在 users/data/list 字段中：{"users": [...]}
        $items = match (true) {
            isset($payload['users']) && is_array($payload['users']) => $payload['users'],
            isset($payload['list']) && is_array($payload['list']) => $payload['list'],
            isset($payload['data']) && is_array($payload['data']) => $payload['data'],
            array_is_list($payload) => $payload,
            default => [],
        };

        $users = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $users[] = PersonnelUser::fromPayload($item);
        }

        return $users;
    }

    private function isAbsoluteUrl(string $value): bool
    {
        // list_path 必须是 path，防止 base_url + full_url 拼出错误请求地址。
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}
