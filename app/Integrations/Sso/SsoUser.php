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
    /**
     * 创建一个 SSO 当前登录人对象。
     *
     * 该对象只表达总部接口返回的登录人信息，不代表 TaskHub 本地角色。
     */
    public function __construct(
        // employeeNo 是 TaskHub 中所有人员引用字段的统一标识。
        private string $employeeNo,
        private ?string $displayName = null,
        private ?string $departmentId = null,
        private ?string $departmentName = null,
        // raw 保留原始响应，便于排查总部接口字段变化。
        private array $raw = [],
    ) {}

    /**
     * 把总部 SSO 当前登录人响应转换为 SsoUser。
     *
     * 当前接口第一层有 id 和 user，人员字段在 user 下；这里统一解析后提供稳定访问方法。
     */
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

    /**
     * 获取总部 SSO 返回的人员工号。
     *
     * 这是 TaskHub 识别当前登录人的基础字段。
     */
    public function employeeNo(): string
    {
        return $this->employeeNo;
    }

    /**
     * 获取总部 SSO 返回的显示名称。
     *
     * 如果本据点人员列表有更准确名称，会额外放在 Session 的 siteUser 中。
     */
    public function displayName(): ?string
    {
        return $this->displayName;
    }

    /**
     * 获取总部 SSO 返回的部门 ID。
     *
     * 该值可能不完整，因此不能作为唯一的本据点人员信息来源。
     */
    public function departmentId(): ?string
    {
        return $this->departmentId;
    }

    /**
     * 获取总部 SSO 返回的部门名称。
     *
     * 页面展示时可以使用它，也可以优先使用 siteUser 中的本据点部门名称。
     */
    public function departmentName(): ?string
    {
        return $this->departmentName;
    }

    /**
     * 获取总部 SSO 原始响应。
     *
     * raw 只用于排查接口字段变化，不建议普通业务代码直接读取。
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * 生成写入 Laravel Session 的总部登录人快照。
     *
     * 登录成功后 Controller 会把该数组保存到 CurrentUserService::SESSION_KEY。
     */
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

    /**
     * 将外部接口字段安全转换为可空字符串。
     *
     * 空字符串统一转为 null，避免 Session 中出现多种“无值”表达。
     */
    private static function nullableString(mixed $value): ?string
    {
        // 空字符串统一视为 null，避免前端同时处理 '' 和 null 两种“无值”状态。
        return is_string($value) && $value !== '' ? $value : null;
    }
}
