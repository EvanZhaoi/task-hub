<?php

namespace App\Integrations\FileOperation;

use RuntimeException;

/**
 * 总部文件上传接口异常。
 *
 * Controller 捕获该异常后，会把错误转换成前端可展示的上传失败信息。
 */
class FileUploadException extends RuntimeException
{
    //
}
