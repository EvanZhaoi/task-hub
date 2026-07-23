// 任务页面展示状态。
// PENDING_SELECTION 是前端派生状态，不是数据库 task.status 枚举值。
export type TaskStatus = 'DRAFT' | 'OPEN' | 'PENDING_SELECTION' | 'ASSIGNED' | 'COMPLETED' | 'FAILED' | 'CANCELLED';

// 数据库真实保存的 task.status，排除前端派生状态。
export type TaskDatabaseStatus = Exclude<TaskStatus, 'PENDING_SELECTION'>;

export type TaskComplexity = 'LOW' | 'MEDIUM' | 'HIGH';

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

export type TaskListItem = {
    // 后端把 BIGINT ID 转成 string，避免 JavaScript number 精度风险。
    id: string;
    title: string;
    // 列表页描述是后端截断后的摘要，不是完整任务说明。
    description: string;
    amountLabel: string;
    // 日期字段已经由后端格式化，前端不再做时区转换。
    expectedDelivery: string | null;
    finalDelivery: string | null;
    biddingDeadline: string | null;
    // displayStatus 允许包含 PENDING_SELECTION。
    displayStatus: TaskStatus;
    // status 是数据库真实状态，不包含 PENDING_SELECTION。
    status: TaskDatabaseStatus;
    assignmentType: 'BIDDING' | 'DIRECT';
    complexity: TaskComplexity;
    // createdBy 是工号，createdByName 是快照中的展示名称。
    createdBy: string;
    createdByName: string;
    departmentName: string | null;
    paymentAccountName: string | null;
    createdAt: string | null;
    activeBidCount: number;
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
    statusOptions: SelectOption[];
    tasks: PaginatedTasks;
};
