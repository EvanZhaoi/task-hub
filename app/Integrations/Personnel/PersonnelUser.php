<?php

namespace App\Integrations\Personnel;

/**
 * 本据点人员列表中的人员值对象。
 *
 * 它不是本地 users 表，也不是 Eloquent Model。
 * TaskHub 只把它作为外部人员主数据的只读结果，用于选择器和登录 Session 信息增强。
 */
final readonly class PersonnelUser
{
    public function __construct(
        // employeeNo 是 TaskHub 中所有人员引用字段的统一标识。
        private string $employeeNo,
        private ?string $displayName = null,
        private ?string $departmentId = null,
        private ?string $departmentName = null,
        // raw 保留原始响应，方便接口字段变化时排查。
        private array $raw = [],
    ) {}

    public static function fromPayload(array $payload): self
    {
        // 人员列表接口可能直接返回人员字段，也可能包一层 user。
        // 这里做轻量兼容，避免页面和 Controller 依赖外部接口的包装结构。
        $user = isset($payload['user']) && is_array($payload['user']) ? $payload['user'] : $payload;

        $employeeNo = self::normalizeEmployeeNo(
            $user['employeeNo'] ?? $user['employee_no'] ?? $user['id'] ?? null,
        );

        if ($employeeNo === null) {
            throw new PersonnelException('Personnel response does not contain employee number.');
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

    public function toOption(): array
    {
        // 未来指定开发者选择器可以直接使用这个结构。
        return array_filter([
            'employeeNo' => $this->employeeNo,
            'displayName' => $this->displayName,
            'departmentId' => $this->departmentId,
            'departmentName' => $this->departmentName,
            'label' => $this->displayName === null
                ? $this->employeeNo
                : "{$this->displayName}（{$this->employeeNo}）",
            'value' => $this->employeeNo,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function toSessionPayload(): array
    {
        // Session 中作为 sso_user.siteUser 保存，表示“本据点人员信息”。
        // 它不覆盖总部 SSO 原始字段，只作为额外展示和后续选择器数据。
        return [
            'employeeNo' => $this->employeeNo,
            'displayName' => $this->displayName,
            'departmentId' => $this->departmentId,
            'departmentName' => $this->departmentName,
            'raw' => $this->raw,
        ];
    }

    public function toCachePayload(): array
    {
        // Redis 中只缓存普通数组，不缓存 PHP 对象，避免类结构变化导致反序列化问题。
        return [
            'employeeNo' => $this->employeeNo,
            'displayName' => $this->displayName,
            'departmentId' => $this->departmentId,
            'departmentName' => $this->departmentName,
        ];
    }

    public static function normalizeEmployeeNo(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        // 本据点工号不带前导 0；总部返回纯数字工号时，统一去掉前导 0 方便匹配本据点人员列表。
        // 带字母的工号例如 E001 不处理，避免误改公司真实工号。
        $normalized = ctype_digit($value) ? ltrim($value, '0') : $value;

        return $normalized === '' ? '0' : $normalized;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
