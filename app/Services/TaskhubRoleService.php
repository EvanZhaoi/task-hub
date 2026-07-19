<?php

namespace App\Services;

use App\Integrations\Sso\SsoUser;

class TaskhubRoleService
{
    /**
     * @return list<string>
     */
    public function rolesFor(SsoUser $user): array
    {
        $employeeNo = $user->employeeNo();
        $roles = [];

        foreach (config('taskhub.roles', []) as $role => $employeeNos) {
            if (in_array($employeeNo, $employeeNos, true)) {
                $roles[] = $role;
            }
        }

        if ($roles === []) {
            $roles[] = (string) config('taskhub.default_role', 'DEVELOPER');
        }

        return array_values(array_unique($roles));
    }
}
