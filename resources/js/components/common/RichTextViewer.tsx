import { cn } from '@/lib/utils';

type RichTextViewerProps = {
    className?: string;
    html?: string | null;
};

export function RichTextViewer({ className, html }: RichTextViewerProps) {
    if (!html) {
        return <p className={cn('text-sm leading-6 text-[#6e6e80]', className)}>暂无描述</p>;
    }

    return (
        <div
            // description 入库前已经由后端 RichTextSanitizer 白名单清洗。
            // Viewer 只负责统一展示样式，不在页面里分散 dangerouslySetInnerHTML。
            className={cn('rich-text-viewer text-sm leading-6 text-[#6e6e80]', className)}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
