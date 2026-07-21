// Inertia 全局共享的页面 props 类型。
// 对应 app/Http/Middleware/HandleInertiaRequests.php 中 share() 返回的 auth 字段。
export type CurrentUser = {
    // employeeNo 是公司工号，也是 TaskHub 中所有人员引用的统一标识。
    employeeNo?: string;
    // displayName 和 departmentName 只用于界面展示，不用于权限判断。
    displayName?: string;
    departmentName?: string;
};

export type SharedPageProps = {
    // auth 来自 HandleInertiaRequests.php，全站页面都可以通过 usePage() 读取。
    auth?: {
        user?: CurrentUser | null;
        // roles 来自本地 taskhub_user_role 表，前端只做展示和轻量 UI 控制。
        roles?: string[];
    };
};
