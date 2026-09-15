import { Paperclip, Upload, X } from 'lucide-react';
import { useRef, useState, type ChangeEvent } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { UploadedTaskAttachment } from '@/types/task';
import { csrfToken } from '@/utils/csrf';

type TaskAttachmentUploaderProps = {
    disabled?: boolean;
    onChange: (files: UploadedTaskAttachment[]) => void;
    value: UploadedTaskAttachment[];
};

type UploadResponse = {
    id?: string;
    name?: string;
};

function errorMessageFromPayload(payload: unknown): string | null {
    // Laravel ValidationException 返回结构通常是 { message, errors: { file: [...] } }。
    // 这里做最小解析，避免假设所有失败响应都是固定格式。
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const errors = 'errors' in payload ? payload.errors : null;

    if (errors && typeof errors === 'object' && 'file' in errors) {
        const fileErrors = errors.file;

        if (Array.isArray(fileErrors) && typeof fileErrors[0] === 'string') {
            return fileErrors[0];
        }
    }

    const message = 'message' in payload ? payload.message : null;

    return typeof message === 'string' && message !== '' ? message : null;
}

async function parseJsonResponse(response: Response): Promise<unknown> {
    // 上传接口正常和失败时都应该返回 JSON；如果外部跳转或服务器错误返回 HTML，也要给出可读错误。
    const contentType = response.headers.get('content-type') ?? '';

    if (!contentType.includes('application/json')) {
        return null;
    }

    return response.json();
}

export function TaskAttachmentUploader({ disabled = false, onChange, value }: TaskAttachmentUploaderProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function openFilePicker(): void {
        // 由自定义按钮触发隐藏 file input，保持 shadcn 风格，同时使用浏览器原生文件选择能力。
        inputRef.current?.click();
    }

    function removeFile(fileId: string): void {
        // 当前 MVP 不调用总部远程删除接口，只从本次发布任务表单中移除该附件 ID。
        onChange(value.filter((file) => file.id !== fileId));
    }

    async function uploadOne(file: File): Promise<UploadedTaskAttachment> {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch('/attachments/upload', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                // 不要手动设置 Content-Type。
                // 浏览器会自动生成 multipart/form-data boundary。
            },
            body: formData,
        });

        const payload = await parseJsonResponse(response);

        if (!response.ok) {
            throw new Error(errorMessageFromPayload(payload) ?? `文件上传失败：HTTP ${response.status}`);
        }

        const uploadedFile = payload as UploadResponse | null;

        if (!uploadedFile?.id || !uploadedFile.name) {
            throw new Error('文件上传成功响应缺少附件 ID 或名称。');
        }

        return {
            id: uploadedFile.id,
            name: uploadedFile.name,
        };
    }

    async function uploadSelectedFiles(event: ChangeEvent<HTMLInputElement>): Promise<void> {
        const selectedFiles = Array.from(event.target.files ?? []);

        // 清空 input 值，允许用户连续选择同一个文件重新上传。
        event.target.value = '';

        if (selectedFiles.length === 0) {
            return;
        }

        setIsUploading(true);
        setError(null);

        const uploadedFiles: UploadedTaskAttachment[] = [];

        try {
            // 当前 MVP 不做并发和进度条，顺序上传更容易定位失败文件。
            for (const file of selectedFiles) {
                uploadedFiles.push(await uploadOne(file));
            }

            // 只有成功上传的文件才加入附件列表；失败文件不会进入发布任务 attachments。
            onChange([...value, ...uploadedFiles]);
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : '文件上传失败，请稍后重试。');
        } finally {
            setIsUploading(false);
        }
    }

    return (
        <div className="space-y-2">
            <input
                className="hidden"
                disabled={disabled || isUploading}
                multiple
                onChange={uploadSelectedFiles}
                ref={inputRef}
                type="file"
            />

            <div className="flex flex-wrap items-center gap-3">
                <Button disabled={disabled || isUploading} onClick={openFilePicker} type="button" variant="outline">
                    <Upload className="mr-2 size-4" />
                    {isUploading ? '上传中...' : '选择文件'}
                </Button>
                <span className="text-xs leading-5 text-[#6e6e80]">
                    选择后立即上传，发布任务时保存附件 ID 和名称。
                </span>
            </div>

            {value.length > 0 ? (
                <div className="space-y-1 rounded-md border border-[#e5e7eb] bg-[#fbfbfc] p-2">
                    {value.map((file) => (
                        <div
                            className="flex min-w-0 items-center justify-between gap-3 rounded-sm bg-white px-2 py-1.5 text-sm"
                            key={file.id}
                        >
                            <span className="flex min-w-0 items-center gap-2">
                                <Paperclip className="size-4 shrink-0 text-[#6e6e80]" />
                                <span className="min-w-0 truncate text-[#374151]">{file.name}</span>
                                <span className="shrink-0 text-xs text-[#9ca3af]">{file.id}</span>
                            </span>
                            <button
                                className={cn(
                                    'shrink-0 cursor-pointer rounded-sm p-1 text-[#6e6e80] hover:bg-[#f3f4f6] hover:text-[#111827]',
                                    disabled && 'cursor-not-allowed opacity-50',
                                )}
                                disabled={disabled}
                                onClick={() => removeFile(file.id)}
                                title="移除附件"
                                type="button"
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    ))}
                </div>
            ) : null}

            {error ? <span className="block text-xs leading-5 text-red-600">{error}</span> : null}
        </div>
    );
}
