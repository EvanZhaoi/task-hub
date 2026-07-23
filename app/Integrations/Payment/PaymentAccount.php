<?php

namespace App\Integrations\Payment;

/**
 * 外部付款账号值对象。
 *
 * TaskHub 不维护付款账号主数据。
 * 发布任务时只把外部接口返回的关键信息保存为历史快照，方便以后展示和审计。
 */
final readonly class PaymentAccount
{
    public function __construct(
        private string $accountId,
        private ?string $accountName = null,
        private ?string $departmentId = null,
        private ?string $departmentName = null,
        private array $raw = [],
    ) {}

    public static function fromPayload(string $requestedAccountId, array $payload): self
    {
        // 兼容两类常见返回：直接返回账号字段，或包在 account/data 字段下。
        $account = match (true) {
            isset($payload['account']) && is_array($payload['account']) => $payload['account'],
            isset($payload['data']) && is_array($payload['data']) => $payload['data'],
            default => $payload,
        };

        $accountId = $account['accountId'] ?? $account['account_id'] ?? $account['id'] ?? $requestedAccountId;

        if (! is_string($accountId) || $accountId === '') {
            throw new PaymentAccountException('Payment account response does not contain account id.');
        }

        return new self(
            accountId: $accountId,
            accountName: self::nullableString($account['accountName'] ?? $account['account_name'] ?? $account['name'] ?? null),
            departmentId: self::nullableString($account['departmentId'] ?? $account['department_id'] ?? null),
            departmentName: self::nullableString($account['departmentName'] ?? $account['department_name'] ?? null),
            raw: $payload,
        );
    }

    /**
     * @return list<self>
     */
    public static function listFromPayload(array $payload): array
    {
        // 外部列表接口常见返回形式可能是：
        // 1. 直接返回账号数组：[{"accountId": "..."}]
        // 2. 包在 data/accounts/list 字段中：{"data": [...]}
        // 这里做轻量兼容，但不把 Controller 和页面绑死在某一种响应包装上。
        $items = match (true) {
            isset($payload['accounts']) && is_array($payload['accounts']) => $payload['accounts'],
            isset($payload['list']) && is_array($payload['list']) => $payload['list'],
            isset($payload['data']) && is_array($payload['data']) => $payload['data'],
            self::isList($payload) => $payload,
            default => [],
        };

        $accounts = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $accounts[] = self::fromPayload('', $item);
        }

        return $accounts;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function accountName(): ?string
    {
        return $this->accountName;
    }

    public function departmentId(): ?string
    {
        return $this->departmentId;
    }

    public function departmentName(): ?string
    {
        return $this->departmentName;
    }

    public function toSnapshot(): array
    {
        // 快照只保存业务展示需要的稳定字段，不把外部接口原始响应整体塞进 task。
        return array_filter([
            'accountId' => $this->accountId,
            'accountName' => $this->accountName,
            'departmentId' => $this->departmentId,
            'departmentName' => $this->departmentName,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function isList(array $payload): bool
    {
        // array_is_list 是 PHP 8.1+ 原生函数，用来判断数组 key 是否为 0..n 的连续数字。
        return array_is_list($payload);
    }
}
