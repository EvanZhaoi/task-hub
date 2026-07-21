<?php

namespace App\Integrations\Sso;

use RuntimeException;

/**
 * SSO 集成异常。
 *
 * 使用独立异常类型后，Controller 可以只捕获 SSO 相关错误，
 * 不会把数据库异常、代码错误等其它问题误当成登录失败。
 */
class SsoException extends RuntimeException {}
