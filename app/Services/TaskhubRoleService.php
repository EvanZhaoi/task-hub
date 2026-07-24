<?php

namespace App\Services;

use App\Integrations\Sso\SsoUser;
use App\Models\TaskhubUserRole;

class TaskhubRoleService
{
    /**
     * 根据 SSO 用户工号查询 TaskHub 本地业务角色。
     *
     * SSO 只证明用户身份，TaskHub 中的 TOP 等业务角色由 taskhub_user_role 表控制。
     *
     * @return list<string>
     */
    public function rolesFor(SsoUser $user): array
    {
        // SSO 只负责“这个人是谁”，TaskHub 业务角色由本系统数据库控制。
        // 使用数据库后，调整发布者/开发者/老板角色不需要修改环境变量或重新发布。
        return TaskhubUserRole::query()
            ->where('employee_no', $user->employeeNo())
            ->where('enabled', true)
            ->orderBy('role')
            ->pluck('role')
            ->filter(fn (mixed $role): bool => is_string($role) && $role !== '')
            ->values()
            ->all();
    }
}
