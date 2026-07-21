export type TaskStatus = 'DRAFT' | 'OPEN' | 'PENDING_SELECTION' | 'ASSIGNED' | 'COMPLETED' | 'FAILED' | 'CANCELLED';

export type TaskDatabaseStatus = Exclude<TaskStatus, 'PENDING_SELECTION'>;

export type TaskComplexity = 'LOW' | 'MEDIUM' | 'HIGH';

export type SelectOption = {
    label: string;
    value: string;
};

export type TaskListItem = {
    id: string;
    title: string;
    description: string;
    amountLabel: string;
    expectedDelivery: string | null;
    finalDelivery: string | null;
    biddingDeadline: string | null;
    displayStatus: TaskStatus;
    status: TaskDatabaseStatus;
    assignmentType: 'BIDDING' | 'DIRECT';
    complexity: TaskComplexity;
    createdBy: string;
    createdByName: string;
    departmentName: string | null;
    paymentAccountName: string | null;
    createdAt: string | null;
    activeBidCount: number;
};

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
        prev: string | null;
        next: string | null;
    };
};

export type TaskFilters = {
    keyword: string;
    status: string;
    complexity: string;
};

export type TaskIndexProps = {
    complexityOptions: SelectOption[];
    filters: TaskFilters;
    statusOptions: SelectOption[];
    tasks: PaginatedTasks;
};
