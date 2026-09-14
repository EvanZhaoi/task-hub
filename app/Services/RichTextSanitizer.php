<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * 富文本 HTML 清洗服务。
 *
 * Tiptap 在前端生成 HTML，但前端永远不是可信边界。
 * 所有 description 入库前都必须经过这里的白名单清洗，避免保存 script、事件属性和危险链接。
 */
class RichTextSanitizer
{
    /**
     * 清洗任务描述富文本。
     *
     * @param  string|null  $html  前端 Tiptap 提交的 HTML 字符串。
     * @return string|null 清洗后的 HTML；空内容返回 null，避免数据库保存无意义空字符串。
     */
    public function clean(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $cleaned = trim($this->purifier()->purify($html));

        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * 创建 HTMLPurifier 实例。
     *
     * 当前只开放 TaskHub 第一版富文本需要的标签：
     * 段落、H2/H3、加粗、斜体、删除线、列表、引用、行内代码、代码块和链接。
     */
    private function purifier(): HTMLPurifier
    {
        $config = HTMLPurifier_Config::createDefault();

        // 只允许 Tiptap 第一版会产生、业务上确实需要展示的标签和属性。
        $config->set(
            'HTML.Allowed',
            implode(',', [
                'p',
                'br',
                'strong',
                'b',
                'em',
                'i',
                's',
                'strike',
                'h2',
                'h3',
                'ul',
                'ol',
                'li',
                'blockquote',
                'code',
                'pre',
                'a[href|target|rel]',
            ]),
        );

        // 链接只允许常见安全协议，禁止 javascript:、data: 等危险 URL。
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);

        // target="_blank" 链接会被 HTMLPurifier 自动补充安全 rel，避免 window.opener 风险。
        $config->set('HTML.TargetBlank', true);

        // 关闭缓存目录写入要求，避免本地没有配置缓存目录时清洗失败。
        $config->set('Cache.DefinitionImpl', null);

        return new HTMLPurifier($config);
    }
}
