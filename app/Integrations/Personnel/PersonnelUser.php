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
    /**
     * 创建一个本据点人员只读对象。
     *
     * 该对象用于表达外部人员列表中的单个人员，不会写入本地 users 表。
     */
    public function __construct(
        // copSort 是真实接口中的公司排序字段，当前 MVP 不参与业务判断。
        private ?int $copSort = null,
        // department 是真实接口中的部门文本，deptInfoList 缺失时作为部门名称兜底。
        private ?string $department = null,
        // deptInfoList 是真实接口中的部门列表，当前 MVP 取第一条有效部门用于展示。
        private array $deptInfoList = [],
        // transferDate 是真实接口中的调动日期，当前 MVP 暂不使用。
        private ?string $transferDate = null,
        // eibClassification 是真实接口中的人员分类，当前 MVP 暂不使用。
        private ?string $eibClassification = null,
        // eibEmail 是真实接口中的邮箱，未来人员选择器可按需展示。
        private ?string $eibEmail = null,
        // eibName 是真实接口中的姓名字段。
        private ?string $eibName = null,
        // eibNameCn 是真实接口中的中文姓名，页面展示优先使用它。
        private ?string $eibNameCn = null,
        // eibNameEn 是真实接口中的英文姓名，当前 MVP 暂不使用。
        private ?string $eibNameEn = null,
        // eibNumCn 是真实接口中的中文工号，TaskHub 会用它作为人员工号来源。
        private ?string $eibNumCn = null,
        // eibNumJp 是真实接口中的日方工号，eibNumCn 缺失时才兜底使用。
        private ?string $eibNumJp = null,
        // eibPhoto 是真实接口中的人员照片地址或标识，当前 MVP 暂不展示。
        private ?string $eibPhoto = null,
        // eibTurnPositiveDate 是真实接口中的转正日期，当前 MVP 暂不使用。
        private ?string $eibTurnPositiveDate = null,
        // eibUserName 是真实接口中的用户名，姓名缺失时可作为展示兜底。
        private ?string $eibUserName = null,
        // eibWorkStatus 是真实接口中的在职状态，当前 MVP 暂不做过滤。
        private ?int $eibWorkStatus = null,
        // firstWorkDate 是真实接口中的首次工作日期，当前 MVP 暂不使用。
        private ?string $firstWorkDate = null,
        // id 是真实接口中的人员记录 ID，工号缺失时才兜底使用。
        private ?string $id = null,
        // joinDate 是真实接口中的入职日期，当前 MVP 暂不使用。
        private ?string $joinDate = null,
        // obiUuid 是真实接口中的组织 UUID，部门列表缺失时可作为部门 ID 兜底。
        private ?string $obiUuid = null,
        // raw 保留原始响应，方便接口字段变化时排查。
        private array $raw = [],
    ) {}

    /**
     * 把外部人员接口返回的单个人员数组转换为 PersonnelUser。
     *
     * 这里统一兼容真实人员列表字段、user 包装和常见字段命名，业务层不需要理解外部响应细节。
     * 真实人员接口字段为 eibNumCn、eibNameCn、deptInfoList 等，这里统一映射为 TaskHub 内部字段。
     */
    public static function fromPayload(array $payload): self
    {
        // 人员列表接口可能直接返回人员字段，也可能包一层 user。
        // 这里做轻量兼容，避免页面和 Controller 依赖外部接口的包装结构。
        $user = isset($payload['user']) && is_array($payload['user']) ? $payload['user'] : $payload;
        $deptInfoList = self::arrayList($user['deptInfoList'] ?? []);

        $employeeNo = self::normalizeEmployeeNo(
            // eibNumCn 是本据点人员接口里的中文工号字段，优先作为 TaskHub 人员工号。
            $user['eibNumCn'] ?? $user['employeeNo'] ?? $user['employee_no'] ?? $user['eibNumJp'] ?? $user['id'] ?? null,
        );

        if ($employeeNo === null) {
            throw new PersonnelException('Personnel response does not contain employee number.');
        }

        return new self(
            copSort: self::nullableInt($user['copSort'] ?? null),
            department: self::nullableString($user['department'] ?? $user['departmentName'] ?? $user['department_name'] ?? null),
            deptInfoList: $deptInfoList,
            transferDate: self::nullableString($user['transferDate'] ?? null),
            eibClassification: self::nullableString($user['eibClassification'] ?? null),
            eibEmail: self::nullableString($user['eibEmail'] ?? null),
            eibName: self::nullableString($user['eibName'] ?? $user['displayName'] ?? $user['display_name'] ?? $user['name'] ?? null),
            eibNameCn: self::nullableString($user['eibNameCn'] ?? null),
            eibNameEn: self::nullableString($user['eibNameEn'] ?? null),
            eibNumCn: self::nullableString($user['eibNumCn'] ?? $user['employeeNo'] ?? $user['employee_no'] ?? null),
            eibNumJp: self::nullableString($user['eibNumJp'] ?? null),
            eibPhoto: self::nullableString($user['eibPhoto'] ?? null),
            eibTurnPositiveDate: self::nullableString($user['eibTurnPositiveDate'] ?? null),
            eibUserName: self::nullableString($user['eibUserName'] ?? null),
            eibWorkStatus: self::nullableInt($user['eibWorkStatus'] ?? null),
            firstWorkDate: self::nullableString($user['firstWorkDate'] ?? null),
            id: self::nullableString($user['id'] ?? null),
            joinDate: self::nullableString($user['joinDate'] ?? null),
            obiUuid: self::nullableString($user['obiUuid'] ?? null),
            raw: $payload,
        );
    }

    /**
     * 获取标准化后的人员工号。
     *
     * TaskHub 所有人员引用字段最终都使用这个工号做业务标识。
     */
    public function employeeNo(): string
    {
        return self::normalizeEmployeeNo($this->eibNumCn ?? $this->eibNumJp ?? $this->id) ?? '';
    }

    /**
     * 获取人员显示名称。
     *
     * 该字段主要用于页面展示和历史快照。
     */
    public function displayName(): ?string
    {
        return $this->eibNameCn ?? $this->eibName ?? $this->eibUserName;
    }

    /**
     * 获取人员所属部门 ID。
     *
     * 外部接口未返回时允许为空。
     */
    public function departmentId(): ?string
    {
        $primaryDepartment = self::primaryDepartment($this->deptInfoList);

        return self::nullableString($primaryDepartment['obiCode'] ?? $primaryDepartment['obiUuid'] ?? $this->obiUuid);
    }

    /**
     * 获取人员所属部门名称。
     *
     * 该字段用于展示，不作为权限判断依据。
     */
    public function departmentName(): ?string
    {
        $primaryDepartment = self::primaryDepartment($this->deptInfoList);

        return self::nullableString($primaryDepartment['obiName'] ?? $this->department);
    }

    /**
     * 获取外部接口原始响应。
     *
     * 保留原始数据是为了排查接口字段变化，不建议业务代码直接依赖 raw。
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * 转换为前端选择器可直接使用的 option 结构。
     *
     * 未来指定开发者时，React Select 或 shadcn Combobox 可以直接消费这个结构。
     */
    public function toOption(): array
    {
        // 未来指定开发者选择器可以直接使用这个结构。
        $displayName = $this->displayName();
        $employeeNo = $this->employeeNo();

        return array_filter([
            'employeeNo' => $employeeNo,
            'displayName' => $displayName,
            'departmentId' => $this->departmentId(),
            'departmentName' => $this->departmentName(),
            'label' => $displayName === null
                ? $employeeNo
                : "{$displayName}（{$employeeNo}）",
            'value' => $employeeNo,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * 生成写入登录 Session 的本据点人员信息。
     *
     * 该结果会作为 sso_user.siteUser 保存，不覆盖总部 SSO 返回的原始登录人信息。
     */
    public function toSessionPayload(): array
    {
        // Session 中作为 sso_user.siteUser 保存，表示“本据点人员信息”。
        // 它不覆盖总部 SSO 原始字段，只作为额外展示和后续选择器数据。
        return [
            'employeeNo' => $this->employeeNo(),
            'displayName' => $this->displayName(),
            'departmentId' => $this->departmentId(),
            'departmentName' => $this->departmentName(),
            'raw' => $this->raw,
        ];
    }

    /**
     * 生成可写入 Redis 的人员缓存数组。
     *
     * 缓存只保存可序列化的普通数组，不保存 PHP 对象。
     */
    public function toCachePayload(): array
    {
        // Redis 中只缓存普通数组，不缓存 PHP 对象，避免类结构变化导致反序列化问题。
        return [
            'copSort' => $this->copSort,
            'department' => $this->department,
            'deptInfoList' => $this->deptInfoList,
            'transferDate' => $this->transferDate,
            'eibClassification' => $this->eibClassification,
            'eibEmail' => $this->eibEmail,
            'eibName' => $this->eibName,
            'eibNameCn' => $this->eibNameCn,
            'eibNameEn' => $this->eibNameEn,
            'eibNumCn' => $this->eibNumCn,
            'eibNumJp' => $this->eibNumJp,
            'eibPhoto' => $this->eibPhoto,
            'eibTurnPositiveDate' => $this->eibTurnPositiveDate,
            'eibUserName' => $this->eibUserName,
            'eibWorkStatus' => $this->eibWorkStatus,
            'firstWorkDate' => $this->firstWorkDate,
            'id' => $this->id,
            'joinDate' => $this->joinDate,
            'obiUuid' => $this->obiUuid,
        ];
    }

    /**
     * 按 TaskHub 本据点规则标准化工号。
     *
     * 纯数字工号会去掉前导 0；包含字母的工号保持原样，避免误改真实工号。
     */
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

    /**
     * 将外部接口字段安全转换为可空字符串。
     *
     * 空字符串统一视为 null，减少调用方对空值的重复判断。
     */
    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * 将外部接口字段安全转换为可空整数。
     *
     * 真实接口中排序和状态字段是数字；如果字段缺失或格式不对，TaskHub 暂时按 null 处理。
     */
    private static function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * 将外部接口中的列表字段规整为数组列表。
     *
     * deptInfoList 必须是数组列表；如果接口返回异常结构，当前 MVP 直接按空列表处理。
     */
    private static function arrayList(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    /**
     * 从真实人员接口的 deptInfoList 中取主要部门。
     *
     * 当前接口返回的是部门列表，TaskHub MVP 只需要一个部门用于展示和快照，所以取第一条有效部门。
     */
    private static function primaryDepartment(array $deptInfoList): array
    {
        foreach ($deptInfoList as $department) {
            if (is_array($department)) {
                return $department;
            }
        }

        return [];
    }
}
