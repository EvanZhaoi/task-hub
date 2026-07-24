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
    /**
     * 创建一个付款账号只读对象。
     *
     * 外部接口返回的数据先被整理成这个对象，再交给 Controller 或快照生成逻辑使用。
     */
    public function __construct(
        private string $accountId,
        private ?string $accountName = null,
        private ?string $departmentId = null,
        private ?string $departmentName = null,
        private array $raw = [],
    ) {}

    /**
     * 把外部接口返回的单个账号 JSON 数组转换为 PaymentAccount 对象。
     *
     * $requestedAccountId 是调用详情接口时传入的账号 ID；如果外部响应里没有账号 ID，
     * 会用它兜底，避免因为接口字段较少导致无法生成账号快照。
     */
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
     * 把外部账号列表响应转换为 PaymentAccount 对象列表。
     *
     * 这个方法兼容 data/accounts/list 等常见包装结构，方便后续对接真实接口时少改业务层。
     *
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

    /**
     * 获取付款账号 ID。
     *
     * 这是业务表 task.payment_account_id 保存的正式查询字段。
     */
    public function accountId(): string
    {
        return $this->accountId;
    }

    /**
     * 获取付款账号名称。
     *
     * 名称主要用于页面展示和任务发布时的历史快照。
     */
    public function accountName(): ?string
    {
        return $this->accountName;
    }

    /**
     * 获取付款账号所属部门 ID。
     *
     * 如果外部接口暂时没有返回部门信息，允许为空。
     */
    public function departmentId(): ?string
    {
        return $this->departmentId;
    }

    /**
     * 获取付款账号所属部门名称。
     *
     * 该字段用于历史展示，不参与权限判断。
     */
    public function departmentName(): ?string
    {
        return $this->departmentName;
    }

    /**
     * 生成写入 task.payment_account_snapshot 的历史快照。
     *
     * 快照用于保存发布任务当时的账号展示信息，避免以后账号改名后历史任务显示变化。
     */
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

    /**
     * 生成可写入 Redis 的付款账号缓存数组。
     *
     * 缓存只保存稳定字段，不缓存整个 PHP 对象，降低后续类结构变化带来的兼容风险。
     */
    public function toCachePayload(): array
    {
        // Redis 中只缓存普通数组，不缓存 PHP 对象，避免类结构变化导致反序列化问题。
        return [
            'accountId' => $this->accountId,
            'accountName' => $this->accountName,
            'departmentId' => $this->departmentId,
            'departmentName' => $this->departmentName,
        ];
    }

    /**
     * 将外部接口字段安全转换为可空字符串。
     *
     * 空字符串会被统一视为 null，减少前端和快照逻辑对空值的分支处理。
     */
    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * 判断一个数组是否是 JSON 列表结构。
     *
     * 外部接口如果直接返回数组列表，就需要通过这个方法识别并逐项转换。
     */
    private static function isList(array $payload): bool
    {
        // array_is_list 是 PHP 8.1+ 原生函数，用来判断数组 key 是否为 0..n 的连续数字。
        return array_is_list($payload);
    }
}
