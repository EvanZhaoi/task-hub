// Inertia 全局共享的页面 props 类型。
// 对应 app/Http/Middleware/HandleInertiaRequests.php 中 share() 返回的 auth 字段。
export type CurrentUser = {
    // employeeNo 是公司工号，也是 TaskHub 中所有人员引用的统一标识。
    employeeNo?: string;
    // displayName、departmentName 和 avatarId 只用于界面展示，不用于权限判断。
    displayName?: string;
    departmentName?: string;
    // avatarId 是总部返回的头像 ID；完整头像 URL 后续由头像服务规则拼接。
    avatarId?: string | null;
    // siteUser 是本据点人员列表匹配到的更完整信息；没有匹配到时不存在。
    siteUser?: {
        employeeNo?: string;
        displayName?: string;
        departmentId?: string;
        departmentName?: string;
        // 头像 ID 来自本据点人员列表，不是 SSO 当前人员接口。
        avatarId?: string | null;
    };
};

export type SharedPageProps = {
    app?: {
        name?: string;
        // 公开 HTTP 图片前缀，UserAvatar 使用它和 avatarId 组合完整地址。
        avatarBaseUrl?: string | null;
    };
    // auth 来自 HandleInertiaRequests.php，全站页面都可以通过 usePage() 读取。
    auth?: {
        user?: CurrentUser | null;
        // roles 来自本地 taskhub_user_role 表，前端只做展示和轻量 UI 控制。
        roles?: string[];
    };
    flash?: {
        // success 来自 Laravel redirect()->with('success', ...)，用于提交成功后的页面提示。
        success?: string | null;
    };
    // Laravel ValidationException 通过 Inertia 共享到 errors，页面用于展示非字段型业务错误。
    errors?: Record<string, string>;
};
