import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Italic, Link2, List, ListOrdered } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type RichTextEditorProps = {
    disabled?: boolean;
    name?: string;
    onChange: (value: string) => void;
    placeholder?: string;
    value: string;
};

type ToolbarButtonProps = {
    active?: boolean;
    disabled?: boolean;
    label: string;
    onClick: () => void;
    children: ReactNode;
};

function ToolbarButton({ active = false, disabled = false, label, onClick, children }: ToolbarButtonProps) {
    return (
        <Button
            aria-label={label}
            className={cn(
                'size-9 border border-transparent bg-transparent px-0 text-[#374151] shadow-none hover:bg-[#f3f4f6] hover:text-[#111827]',
                active && 'bg-[#f5f3ff] text-[#5e6ad2] hover:bg-[#f5f3ff] hover:text-[#5e6ad2]',
            )}
            disabled={disabled}
            onClick={onClick}
            title={label}
            type="button"
            variant="ghost"
        >
            {children}
        </Button>
    );
}

export function RichTextEditor({
    disabled = false,
    name,
    onChange,
    placeholder = '说明背景、目标、验收标准和注意事项',
    value,
}: RichTextEditorProps) {
    const editor = useEditor({
        content: value,
        editable: !disabled,
        extensions: [
            StarterKit.configure({
                // 继续保留 StarterKit 的默认能力，后续需要恢复标题、引用、代码块时无需重做编辑器结构。
                // 第一版发布表单只在工具栏暴露最常用的 5 个按钮，降低填写任务时的视觉负担。
                heading: {
                    levels: [2, 3],
                },
            }),
            Link.configure({
                // 链接点击不直接跳转，避免编辑时误打开页面。
                openOnClick: false,
                // 自动给链接补充安全属性；后端仍会再次白名单清洗。
                HTMLAttributes: {
                    rel: 'noopener noreferrer',
                    target: '_blank',
                },
            }),
            Placeholder.configure({
                // Placeholder 只影响编辑时的空内容提示，不会写入最终 HTML。
                placeholder,
            }),
        ],
        editorProps: {
            attributes: {
                class: cn('min-h-28 rounded-b-md px-3 py-2 text-sm leading-6 outline-none', 'prose-taskhub max-w-none'),
            },
        },
        immediatelyRender: false,
        onUpdate: ({ editor }) => {
            // description 继续以 HTML 字符串提交给现有 Laravel 接口。
            onChange(editor.getHTML());
        },
    });

    useEffect(() => {
        if (!editor || editor.getHTML() === value) {
            return;
        }

        // Inertia form reset 后，父组件 value 会变为空字符串；这里同步清空编辑器内容。
        editor.commands.setContent(value, { emitUpdate: false });
    }, [editor, value]);

    useEffect(() => {
        // disabled 状态变化时同步给 Tiptap，避免提交中仍可编辑。
        editor?.setEditable(!disabled);
    }, [disabled, editor]);

    function setLink(): void {
        if (!editor) {
            return;
        }

        const previousUrl = editor.getAttributes('link').href as string | undefined;
        const nextUrl = window.prompt('请输入链接地址', previousUrl ?? 'https://');

        if (nextUrl === null) {
            return;
        }

        if (nextUrl.trim() === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        // 前端只做基础体验校验；真正安全边界仍在后端 RichTextSanitizer。
        editor.chain().focus().extendMarkRange('link').setLink({ href: nextUrl.trim() }).run();
    }

    return (
        <div className="rounded-md border border-[#d1d5db] bg-white focus-within:border-[#5e6ad2] focus-within:ring-2 focus-within:ring-[#5e6ad2]/15">
            <div className="flex flex-wrap items-center gap-1 border-b border-[#eef0f3] bg-[#fbfbfc] px-2 py-1.5">
                <ToolbarButton
                    active={editor?.isActive('bold')}
                    disabled={disabled || !editor}
                    label="粗体"
                    onClick={() => editor?.chain().focus().toggleBold().run()}
                >
                    <Bold className="size-[22px]" strokeWidth={2.25} />
                </ToolbarButton>
                <ToolbarButton
                    active={editor?.isActive('italic')}
                    disabled={disabled || !editor}
                    label="斜体"
                    onClick={() => editor?.chain().focus().toggleItalic().run()}
                >
                    <Italic className="size-[22px]" strokeWidth={2.25} />
                </ToolbarButton>
                <ToolbarButton
                    active={editor?.isActive('bulletList')}
                    disabled={disabled || !editor}
                    label="无序列表"
                    onClick={() => editor?.chain().focus().toggleBulletList().run()}
                >
                    <List className="size-[22px]" strokeWidth={2.25} />
                </ToolbarButton>
                <ToolbarButton
                    active={editor?.isActive('orderedList')}
                    disabled={disabled || !editor}
                    label="有序列表"
                    onClick={() => editor?.chain().focus().toggleOrderedList().run()}
                >
                    <ListOrdered className="size-[22px]" strokeWidth={2.25} />
                </ToolbarButton>
                <ToolbarButton
                    active={editor?.isActive('link')}
                    disabled={disabled || !editor}
                    label="链接"
                    onClick={setLink}
                >
                    <Link2 className="size-[22px]" strokeWidth={2.25} />
                </ToolbarButton>
            </div>

            <EditorContent editor={editor} />

            {/* 保留 name 字段，未来如果改回原生表单提交，也能继续提交 description。 */}
            {name ? <input name={name} type="hidden" value={value} /> : null}
        </div>
    );
}
