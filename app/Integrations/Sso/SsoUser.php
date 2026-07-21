<?php

namespace App\Integrations\Sso;

/**
 * SSO 当前登录人值对象。
 *
 * 这个对象只表示“公司人员接口返回的人”，不是 Eloquent Model，也不对应本地 users 表。
 * readonly 保证对象创建后不可变，避免请求处理中被其它代码意外改写。
 */
final readonly class SsoUser
{
    public function __construct(
        // employeeNo 是 TaskHub 中所有人员引用字段的统一标识。
        private string $employeeNo,
        private ?string $displayName = null,
        private ?string $departmentId = null,
        private ?string $departmentName = null,
        // raw 保留原始响应，便于排查总部接口字段变化。
        private array $raw = [],
    ) {}

    public static function fromPayload(array $payload): self
    {
        // 公司当前登录人接口第一层返回 id 和 user，真实人员属性在 user 下。
        // Session 中保存的是扁平结构，因此这里保留扁平结构兼容读取。
        $user = isset($payload['user']) && is_array($payload['user']) ? $payload['user'] : $payload;

        // 兼容不同命名：优先 employeeNo，其次 snake_case，再退回 id。
        $employeeNo = $user['employeeNo'] ?? $user['employee_no'] ?? $user['id'] ?? $payload['id'] ?? null;

        if (! is_string($employeeNo) || $employeeNo === '') {
            throw new SsoException('SSO response does not contain employee number.');
        }

        return new self(
            employeeNo: $employeeNo,
            displayName: self::nullableString($user['displayName'] ?? $user['display_name'] ?? $user['name'] ?? null),
            departmentId: self::nullableString($user['departmentId'] ?? $user['department_id'] ?? null),
            departmentName: self::nullableString($user['departmentName'] ?? $user['department_name'] ?? null),
            raw: $payload,
        );
    }

    public function employeeNo(): string
    {
        return $this->employeeNo;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function departmentId(): ?string
    {
        return $this->departmentId;
    }

    public function departmentName(): ?string
    {
        return $this->departmentName;
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function toSessionPayload(): array
    {
        // Session 中只保存页面展示和后续请求需要的最小用户快照。
        return [
            'employeeNo' => $this->employeeNo,
            'displayName' => $this->displayName,
            'departmentId' => $this->departmentId,
            'departmentName' => $this->departmentName,
            'raw' => $this->raw,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        // 空字符串统一视为 null，避免前端同时处理 '' 和 null 两种“无值”状态。
        return is_string($value) && $value !== '' ? $value : null;
    }
}
