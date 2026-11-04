import {
    File,
    FileArchive,
    FileCode,
    FileImage,
    FileMusic,
    FileSpreadsheet,
    FileText,
    FileType,
    FileVideoCamera,
    Presentation,
    type LucideIcon,
} from 'lucide-react';

import { cn } from '@/lib/utils';

type AttachmentIconProps = {
    className?: string;
    fileName: string;
};

type FileIconConfig = {
    className: string;
    icon: LucideIcon;
    label: string;
};

// 扩展名分组集中维护，后续增加新格式时不需要修改附件列表组件。
const wordExtensions = new Set(['doc', 'docx', 'odt', 'rtf']);
const spreadsheetExtensions = new Set(['csv', 'ods', 'xls', 'xlsm', 'xlsx']);
const presentationExtensions = new Set(['key', 'odp', 'ppt', 'pptx']);
const imageExtensions = new Set(['avif', 'bmp', 'gif', 'heic', 'jpeg', 'jpg', 'png', 'svg', 'tif', 'tiff', 'webp']);
const archiveExtensions = new Set(['7z', 'bz2', 'gz', 'rar', 'tar', 'tgz', 'xz', 'zip']);
const audioExtensions = new Set(['aac', 'flac', 'm4a', 'mp3', 'ogg', 'wav', 'wma']);
const videoExtensions = new Set(['avi', 'mkv', 'mov', 'mp4', 'mpeg', 'mpg', 'webm', 'wmv']);
const codeExtensions = new Set([
    'css',
    'go',
    'html',
    'java',
    'js',
    'json',
    'jsx',
    'md',
    'php',
    'py',
    'rb',
    'rs',
    'sh',
    'sql',
    'ts',
    'tsx',
    'vue',
    'xml',
    'yaml',
    'yml',
]);
const textExtensions = new Set(['ini', 'log', 'properties', 'txt']);

/**
 * 从文件名中读取最后一个扩展名。
 *
 * 总部返回的是文件名称而不是 MIME Type，因此第一版按扩展名判断；
 * 没有扩展名、以点结尾或无法识别时都会回退到普通文件图标。
 */
function fileExtension(fileName: string): string {
    const normalizedName = fileName.trim().toLowerCase();
    const lastDot = normalizedName.lastIndexOf('.');

    if (lastDot <= 0 || lastDot === normalizedName.length - 1) {
        return '';
    }

    return normalizedName.slice(lastDot + 1);
}

/**
 * 根据扩展名选择语义图标和轻量类型色。
 *
 * 颜色只用于提高扫描效率，文件类型的最终判断仍以图标和文件名为准。
 */
function iconConfig(fileName: string): FileIconConfig {
    const extension = fileExtension(fileName);

    if (extension === 'pdf') {
        return { className: 'text-red-600', icon: FileType, label: 'PDF' };
    }

    if (wordExtensions.has(extension)) {
        return { className: 'text-blue-600', icon: FileText, label: '文档' };
    }

    if (spreadsheetExtensions.has(extension)) {
        return { className: 'text-emerald-600', icon: FileSpreadsheet, label: '表格' };
    }

    if (presentationExtensions.has(extension)) {
        return { className: 'text-orange-600', icon: Presentation, label: '演示文稿' };
    }

    if (imageExtensions.has(extension)) {
        return { className: 'text-violet-600', icon: FileImage, label: '图片' };
    }

    if (archiveExtensions.has(extension)) {
        return { className: 'text-amber-600', icon: FileArchive, label: '压缩包' };
    }

    if (audioExtensions.has(extension)) {
        return { className: 'text-pink-600', icon: FileMusic, label: '音频' };
    }

    if (videoExtensions.has(extension)) {
        return { className: 'text-indigo-600', icon: FileVideoCamera, label: '视频' };
    }

    if (codeExtensions.has(extension)) {
        return { className: 'text-cyan-700', icon: FileCode, label: '代码' };
    }

    if (textExtensions.has(extension)) {
        return { className: 'text-slate-600', icon: FileText, label: '文本' };
    }

    return { className: 'text-slate-500', icon: File, label: '文件' };
}

/**
 * 展示与附件类型匹配的图标。
 *
 * 组件只负责视觉识别，不参与上传、下载或文件类型安全校验。
 */
export function AttachmentIcon({ className, fileName }: AttachmentIconProps) {
    const config = iconConfig(fileName);
    const Icon = config.icon;

    return (
        <span className="inline-flex shrink-0" title={`${config.label}文件`}>
            <Icon
                aria-hidden="true"
                className={cn('size-5', config.className, className)}
                strokeWidth={2}
            />
        </span>
    );
}
