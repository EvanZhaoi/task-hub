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
        // wbaAccountCode 是真实接口里的交易/付款账号编码。
        private string $wbaAccountCode,
        // wbaAccountName 是真实接口里的交易/付款账号名称。
        private ?string $wbaAccountName = null,
        // deptName 是真实接口里的部门名称；接口没有单独给部门 ID。
        private ?string $deptName = null,
        // wbaType 是真实接口里的账号类型，当前 MVP 暂不参与业务判断。
        private ?string $wbaType = null,
        private array $raw = [],
    ) {}

    /**
     * 把真实付款账号列表接口的完整响应转换为 PaymentAccount 列表。
     *
     * 这个方法的入参预计是外部 API 直接返回的完整 JSON 数组，例如：
     * {"code": "", "data": [{...账号字段...}], "msg": "", "timestamp": 0, "total": 0}。
     *
     * @return list<self>
     */
    public static function listFromPayload(array $payload): array
    {
        // 真实接口返回 data 数组；array_is_list 兼容 Redis 中已经缓存成列表的结构。
        $items = match (true) {
            isset($payload['data']) && is_array($payload['data']) => $payload['data'],
            array_is_list($payload) => $payload,
            default => [],
        };

        $accounts = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $accounts[] = self::fromItem($item);
        }

        return $accounts;
    }

    /**
     * 把 data 数组中的一条账号记录转换为 PaymentAccount。
     *
     * 该方法是私有方法，只服务于 listFromPayload()；外部代码不应该直接调用它。
     */
    private static function fromItem(array $account): self
    {
        // wbaAccountCode 是真实接口里的交易/付款账号编码，TaskHub 内部统一叫 accountId。
        $accountId = $account['wbaAccountCode']
            ?? $account['accountId']
            ?? $account['account_id']
            ?? $account['id']
            ?? null;

        if (! is_string($accountId) || $accountId === '') {
            throw new PaymentAccountException('Payment account response does not contain account id.');
        }

        return new self(
            wbaAccountCode: $accountId,
            // wbaAccountName 是真实接口里的账号名称，TaskHub 内部统一叫 accountName。
            wbaAccountName: self::nullableString($account['wbaAccountName'] ?? $account['accountName'] ?? $account['account_name'] ?? $account['name'] ?? null),
            // deptName 是真实接口里的部门名称；接口未提供部门 ID，因此 departmentId 可以为空。
            deptName: self::nullableString($account['deptName'] ?? $account['departmentName'] ?? $account['department_name'] ?? null),
            wbaType: self::nullableString($account['wbaType'] ?? null),
            raw: $account,
        );
    }

    /**
     * 获取真实接口字段 wbaAccountCode。
     *
     * 该字段是交易/付款账号编码，也是 TaskHub 内部 accountId 的来源。
     */
    public function wbaAccountCode(): string
    {
        return $this->wbaAccountCode;
    }

    /**
     * 获取真实接口字段 wbaAccountName。
     *
     * 该字段是交易/付款账号名称，也是 TaskHub 内部 accountName 的来源。
     */
    public function wbaAccountName(): ?string
    {
        return $this->wbaAccountName;
    }

    /**
     * 获取真实接口字段 deptName。
     *
     * 该字段是账号所属部门名称，也是 TaskHub 内部 departmentName 的来源。
     */
    public function deptName(): ?string
    {
        return $this->deptName;
    }

    /**
     * 获取真实接口字段 wbaType。
     *
     * 当前 MVP 暂不使用账号类型做业务判断，但保留读取方法方便后续扩展。
     */
    public function wbaType(): ?string
    {
        return $this->wbaType;
    }

    /**
     * 获取付款账号 ID。
     *
     * 这是业务表 task.payment_account_id 保存的正式查询字段。
     */
    public function accountId(): string
    {
        return $this->wbaAccountCode();
    }

    /**
     * 获取付款账号名称。
     *
     * 名称主要用于页面展示和任务发布时的历史快照。
     */
    public function accountName(): ?string
    {
        return $this->wbaAccountName();
    }

    /**
     * 获取付款账号所属部门 ID。
     *
     * 如果外部接口暂时没有返回部门信息，允许为空。
     */
    public function departmentId(): ?string
    {
        return null;
    }

    /**
     * 获取付款账号所属部门名称。
     *
     * 该字段用于历史展示，不参与权限判断。
     */
    public function departmentName(): ?string
    {
        return $this->deptName();
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
            'accountId' => $this->accountId(),
            'accountName' => $this->accountName(),
            'departmentId' => $this->departmentId(),
            'departmentName' => $this->departmentName(),
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
            'deptName' => $this->deptName,
            'wbaAccountCode' => $this->wbaAccountCode,
            'wbaAccountName' => $this->wbaAccountName,
            'wbaType' => $this->wbaType,
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
}
