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
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $employeeNo = $payload['employeeNo'] ?? $payload['employee_no'] ?? null;

        if (! is_string($employeeNo) || $employeeNo === '') {
            throw new SsoException('SSO response does not contain employee number.');
        }

        return new self(
            employeeNo: $employeeNo,
            displayName: self::nullableString($payload['displayName'] ?? $payload['display_name'] ?? null),
            departmentId: self::nullableString($payload['departmentId'] ?? $payload['department_id'] ?? null),
            departmentName: self::nullableString($payload['departmentName'] ?? $payload['department_name'] ?? null),
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

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
