// 任务页面展示状态。
// PENDING_SELECTION 是前端派生状态，不是数据库 task.status 枚举值。
export type TaskStatus = 'DRAFT' | 'OPEN' | 'PENDING_SELECTION' | 'ASSIGNED' | 'COMPLETED' | 'FAILED' | 'CANCELLED';

// 数据库真实保存的 task.status，排除前端派生状态。
export type TaskDatabaseStatus = Exclude<TaskStatus, 'PENDING_SELECTION'>;

export type TaskComplexity = 'LOW' | 'MEDIUM' | 'HIGH';

export type TaskAssignmentType = 'BIDDING' | 'DIRECT';

export type SelectOption = {
    // label 是展示文案，value 是提交给后端的筛选值。
    label: string;
    value: string;
};

export type PaymentAccountOption = SelectOption & {
    // 账号名称和部门名称只用于选择器辅助展示，保存时后端会重新查询外部接口。
    accountName?: string | null;
    departmentName?: string | null;
};

export type UploadedTaskAttachment = {
    // id 是总部上传接口返回的 data.id，后续下载/预览时使用。
    id: string;
    // name 是总部上传接口返回的 data.name，发布任务时写入 attachment_ref.attachment_name。
    name: string;
};

export type PersonnelOption = {
    // employeeNo 是 TaskHub 保存到人员引用字段的稳定工号。
    employeeNo: string;
    // 姓名和部门只用于选择器及历史记录展示，权限仍以后端实时规则为准。
    displayName: string;
    departmentName: string | null;
};

export type TaskAttachment = {
    // attachment_ref.attachment_id，真实文件仍在总部文件服务中。
    id: string;
    // attachment_ref.attachment_name，详情页直接展示文件名。
    name: string;
    // Laravel 使用配置的固定前缀和附件 ID 生成链接；未配置时返回 null。
    url: string | null;
};

export type TaskListItem = {
    // 后端把 BIGINT ID 转成 string，避免 JavaScript number 精度风险。
    id: string;
    title: string;
    // 列表页描述是后端截断后的摘要，不是完整任务说明。
    description: string;
    amountLabel: string;
    // FINAL 表示已经通过选标或后续协商形成成交金额；否则当前值仍是发布预算。
    amountType: 'BUDGET' | 'FINAL';
    // 日期字段已经由后端格式化，前端不再做时区转换。
    expectedDelivery: string | null;
    finalDelivery: string | null;
    biddingDeadline: string | null;
    // displayStatus 允许包含 PENDING_SELECTION。
    displayStatus: TaskStatus;
    // status 是数据库真实状态，不包含 PENDING_SELECTION。
    status: TaskDatabaseStatus;
    assignmentType: TaskAssignmentType;
    complexity: TaskComplexity;
    isUrgent: boolean;
    // createdBy 是工号，createdByName 是快照中的展示名称。
    createdBy: string;
    createdByName: string;
    // 来自本据点人员目录 eibPhoto；缺失时头像组件显示姓名首字符。
    createdByAvatarId: string | null;
    departmentName: string | null;
    paymentAccountName: string | null;
    createdAt: string | null;
    // activeBidCount 表示当前可选的方案；bidCount 表示历史累计提交数。
    activeBidCount: number;
    bidCount: number;
};

// Laravel paginate() 转换后传给前端的分页元数据。
export type PaginationMeta = {
    currentPage: number;
    from: number | null;
    lastPage: number;
    perPage: number;
    to: number | null;
    total: number;
};

// 新增列表页共用 Laravel 分页外形，但每个页面仍保留自己的业务记录类型。
export type Paginated<T> = {
    data: T[];
    meta: PaginationMeta;
    links: {
        prev: string | null;
        next: string | null;
    };
};

export type PaginatedTasks = {
    data: TaskListItem[];
    meta: PaginationMeta;
    links: {
        // Laravel paginator 生成的上一页/下一页 URL，已经带上当前 query string。
        prev: string | null;
        next: string | null;
    };
};

export type TaskFilters = {
    // 与任务大厅 GET 表单字段一一对应。
    keyword: string;
    status: string;
    complexity: string;
};

export type TaskIndexProps = {
    // 这些 props 由 TaskController@index 通过 Inertia::render() 下发。
    complexityOptions: SelectOption[];
    filters: TaskFilters;
    paymentAccountOptions: PaymentAccountOption[];
    // 直接指派模式从本据点人员目录选择负责人和协作成员。
    personnelOptions: PersonnelOption[];
    statusOptions: SelectOption[];
    tasks: PaginatedTasks;
};

export type BidStatus = 'ACTIVE' | 'WITHDRAWN' | 'ACCEPTED' | 'LOST';

export type BidMemberRole = 'OWNER' | 'COLLABORATOR';

export type BidMemberItem = {
    // userId 保存人员工号，不是本地 users 表 ID。
    userId: string;
    displayName: string;
    departmentName: string | null;
    avatarId: string | null;
    role: BidMemberRole;
};

export type BidItem = {
    // 后端把 BIGINT 转成 string，避免 JavaScript 精度风险。
    id: string;
    amount: string;
    amountLabel: string;
    deliveryDate: string | null;
    proposal: string | null;
    status: BidStatus;
    // canSelect 由后端根据发布者、Task 状态和 Bid 状态计算；前端不重复维护权限规则。
    canSelect: boolean;
    // canWithdraw 只对截止前 ACTIVE 投标的 OWNER 为 true，真正校验仍以后端事务为准。
    canWithdraw: boolean;
    revisionNo: number;
    createdAt: string | null;
    withdrawnAt: string | null;
    members: BidMemberItem[];
    attachments: TaskAttachment[];
};

export type TaskAssigneeItem = BidMemberItem;

export type TaskEventItem = {
    id: string;
    eventType: string;
    operatorId: string | null;
    operatorName: string | null;
    fromStatus: TaskDatabaseStatus | null;
    toStatus: TaskDatabaseStatus | null;
    relatedType: string | null;
    relatedId: string | null;
    remark: string | null;
    createdAt: string | null;
};

export type TaskDetail = {
    id: string;
    title: string;
    description: string;
    amountLabel: string;
    amountType: 'BUDGET' | 'FINAL';
    budget: string;
    finalAmount: string | null;
    expectedDelivery: string | null;
    finalDelivery: string | null;
    biddingDeadline: string | null;
    displayStatus: TaskStatus;
    status: TaskDatabaseStatus;
    assignmentType: TaskAssignmentType;
    complexity: TaskComplexity;
    isUrgent: boolean;
    createdBy: string;
    createdByName: string;
    createdByAvatarId: string | null;
    departmentName: string | null;
    paymentAccountId: string;
    paymentAccountName: string | null;
    paymentDepartmentName: string | null;
    createdAt: string | null;
    updatedAt: string | null;
    activeBidCount: number;
    isCreatedByCurrentUser: boolean;
    // 正式执行成员来自 task_assignee，不从中标 Bid 临时推导。
    assignees: TaskAssigneeItem[];
    currentUserAssigneeRole: BidMemberRole | null;
    attachments: TaskAttachment[];
};

export type TaskShowProps = {
    // bidOverview 只公开数量，不包含无权查看的投标人、报价、说明和附件。
    bidOverview: {
        // activeCount 是当前仍可参与选标的投标数，不包含撤回、中标或未中标记录。
        activeCount: number;
        hiddenCount: number;
        // totalCount 不包含 WITHDRAWN，撤回历史数量不会暴露给无关用户。
        totalCount: number;
        visibleCount: number;
    };
    canPlaceBid: boolean;
    currentEmployeeNo: string;
    events: TaskEventItem[];
    personnelOptions: PersonnelOption[];
    navigation: {
        // activeNav 控制详情页顶部应高亮“任务大厅”还是“我的任务”。
        activeNav: 'tasks' | 'my-tasks';
        backLabel: string;
        backUrl: string;
        // returnTo 会随投标和选标表单继续提交，成功后仍可回到原筛选页。
        returnTo: string;
    };
    task: TaskDetail;
    visibleBids: BidItem[];
};

export type PersonalTaskFilters = {
    keyword: string;
    status: string;
};

export type PublishedTaskItem = {
    id: string;
    title: string;
    description: string;
    amountLabel: string;
    amountType: 'BUDGET' | 'FINAL';
    displayStatus: TaskStatus;
    status: TaskDatabaseStatus;
    assignmentType: TaskAssignmentType;
    complexity: TaskComplexity;
    isUrgent: boolean;
    expectedDelivery: string | null;
    finalDelivery: string | null;
    biddingDeadline: string | null;
    createdAt: string | null;
    activeBidCount: number;
    bidCount: number;
};

export type PublishedTasksProps = {
    filters: PersonalTaskFilters;
    tasks: Paginated<PublishedTaskItem>;
};

export type MyBidMemberItem = {
    userId: string;
    displayName: string;
    role: BidMemberRole;
};

export type MyBidItem = {
    id: string;
    taskId: string;
    taskTitle: string;
    taskStatus: TaskDatabaseStatus | null;
    status: BidStatus;
    role: BidMemberRole;
    amountLabel: string;
    deliveryDate: string | null;
    proposal: string | null;
    revisionNo: number;
    createdAt: string | null;
    members: MyBidMemberItem[];
};

export type MyBidsProps = {
    bids: Paginated<MyBidItem>;
    filters: PersonalTaskFilters;
};

export type AssignedTaskItem = {
    taskId: string;
    title: string;
    description: string;
    status: Extract<TaskDatabaseStatus, 'ASSIGNED' | 'COMPLETED' | 'CANCELLED'>;
    role: BidMemberRole;
    amountLabel: string;
    finalDelivery: string | null;
    complexity: TaskComplexity;
    isUrgent: boolean;
    updatedAt: string | null;
    assignees: MyBidMemberItem[];
};

export type AssignedTasksProps = {
    assignments: Paginated<AssignedTaskItem>;
    filters: PersonalTaskFilters;
};
