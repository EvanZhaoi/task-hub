<?php

namespace App\Integrations\Sso;

final readonly class SsoUser
{
    public function __construct(
        private string $employeeNo,
        private ?string $displayName = null,
        private ?string $departmentId = null,
        private ?string $departmentName = null,
        private array $raw = [],
    ) {}

    public static function fromPayload(array $payload): self
    {
        // 公司当前登录人接口第一层返回 id 和 user，真实人员属性在 user 下。
        // Session 中保存的是扁平结构，因此这里保留扁平结构兼容读取。
        $user = isset($payload['user']) && is_array($payload['user']) ? $payload['user'] : $payload;

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
        return is_string($value) && $value !== '' ? $value : null;
    }
}
