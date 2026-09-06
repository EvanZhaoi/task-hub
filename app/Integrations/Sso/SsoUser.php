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
     * 当前人员信息接口第一层是 code、data、msg、timestamp、total。
     * data 是人员数组；当前登录人接口通常只返回一条人员记录，所以 TaskHub 取 data[0]。
     * 为了兼容旧 Session 和旧接口，也保留 user 嵌套结构和扁平结构解析。
     */
    public static function fromPayload(array $payload): self
    {
        // 新接口返回 data 数组，当前登录人信息取第一条。
        // 旧接口返回 user 对象；Session 中保存的是扁平结构。这里三个来源都兼容。
        $user = self::firstUserPayload($payload);

        // 工号字段优先取中方工号，其次兼容旧 employeeNo 和其它可能命名。
        $employeeNo = $user['empCnNum']
            ?? $user['empNumCn']
            ?? $user['employeeNo']
            ?? $user['employee_no']
            ?? $user['empNo']
            ?? $user['id']
            ?? $payload['id']
            ?? null;

        if (! is_string($employeeNo) || $employeeNo === '') {
            throw new SsoException('SSO response does not contain employee number.');
        }

        $primaryDepartment = self::primaryDepartment($user['deptInfoList'] ?? []);

        return new self(
            employeeNo: $employeeNo,
            displayName: self::nullableString(
                $user['empName']
                ?? $user['empNameCn']
                ?? $user['displayName']
                ?? $user['display_name']
                ?? $user['name']
                ?? null
            ),
            departmentId: self::nullableString(
                $primaryDepartment['obiCode']
                ?? $primaryDepartment['obCode']
                ?? $primaryDepartment['obiUuid']
                ?? $user['departmentId']
                ?? $user['department_id']
                ?? null
            ),
            departmentName: self::nullableString(
                $primaryDepartment['obiName']
                ?? $primaryDepartment['obName']
                ?? $user['department']
                ?? $user['departmentName']
                ?? $user['department_name']
                ?? null
            ),
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

    /**
     * 从当前人员信息接口响应中取出单条人员数据。
     *
     * 新接口：payload.data 是数组，取第一条。
     * 旧接口：payload.user 是对象。
     * Session：payload 本身就是扁平人员数组。
     */
    private static function firstUserPayload(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            $first = $payload['data'][0] ?? null;

            if (is_array($first)) {
                return $first;
            }
        }

        if (isset($payload['user']) && is_array($payload['user'])) {
            return $payload['user'];
        }

        return $payload;
    }

    /**
     * 从 deptInfoList 中取主要部门。
     *
     * 当前 MVP 只需要一个部门用于展示和历史快照。
     * 当接口返回多个部门时，按用户确认的规则选择 copSort 数字最小的那一条。
     */
    private static function primaryDepartment(mixed $deptInfoList): array
    {
        if (! is_array($deptInfoList)) {
            return [];
        }

        $selected = null;
        $selectedSort = null;

        foreach ($deptInfoList as $department) {
            if (! is_array($department)) {
                continue;
            }

            // copSort 表示部门排序优先级；数字越小优先级越高。
            // 如果字段缺失或不是数字，放到最后，仅在没有有效 copSort 时兜底使用。
            $sort = is_numeric($department['copSort'] ?? null)
                ? (int) $department['copSort']
                : PHP_INT_MAX;

            if ($selected === null || $sort < $selectedSort) {
                $selected = $department;
                $selectedSort = $sort;
            }
        }

        return $selected ?? [];
    }
}
