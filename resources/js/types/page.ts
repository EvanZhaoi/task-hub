// Inertia 全局共享的页面 props 类型。
// 对应 app/Http/Middleware/HandleInertiaRequests.php 中 share() 返回的 auth 字段。
export type CurrentUser = {
    employeeNo?: string;
    displayName?: string;
    departmentName?: string;
};

export type SharedPageProps = {
    auth?: {
        user?: CurrentUser | null;
        roles?: string[];
    };
};
