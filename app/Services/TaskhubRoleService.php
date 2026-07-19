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
