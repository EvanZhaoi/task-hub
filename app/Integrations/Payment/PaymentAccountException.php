<?php

namespace App\Integrations\Payment;

use RuntimeException;

/**
 * 付款账号外部接口异常。
 *
 * 使用独立异常后，Controller 可以把账号查询失败转换成表单错误，
 * 不会误认为是数据库写入失败或代码异常。
 */
class PaymentAccountException extends RuntimeException {}
