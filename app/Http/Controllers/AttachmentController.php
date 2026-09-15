<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadAttachmentRequest;
use App\Integrations\FileOperation\FileUploadClient;
use App\Integrations\FileOperation\FileUploadException;
use App\Services\CurrentUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * 附件控制器。
 *
 * 当前只提供发布任务前的单文件上传入口。
 * Controller 不直接调用总部接口细节，只把文件和当前用户 token 交给 FileUploadClient。
 */
class AttachmentController extends Controller
{
    /**
     * 上传一个附件到总部文件服务。
     *
     * React 先把文件传到 TaskHub 后端，Laravel 再携带当前 Session 中的 accessToken 调总部接口。
     */
    public function upload(
        UploadAttachmentRequest $request,
        CurrentUserService $currentUser,
        FileUploadClient $files,
    ): JsonResponse {
        try {
            $uploadedFile = $files->upload(
                file: $request->file('file'),
                accessToken: $currentUser->accessToken(),
            );
        } catch (FileUploadException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'file' => $uploadedFile->toResponsePayload(),
        ]);
    }
}
