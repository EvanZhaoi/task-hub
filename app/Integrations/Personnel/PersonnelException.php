<?php

namespace App\Integrations\Personnel;

use RuntimeException;

/**
 * 本据点人员列表外部接口异常。
 *
 * 单独异常可以让登录流程区分“总部 SSO 身份失败”和“本据点人员增强失败”。
 */
class PersonnelException extends RuntimeException {}
