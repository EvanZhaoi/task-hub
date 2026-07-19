<?php

namespace App\Services;

use App\Integrations\Sso\SsoUser;
use App\Models\TaskhubUserRole;

class TaskhubRoleService
{
    /**
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
