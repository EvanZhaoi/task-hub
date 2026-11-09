<?php

namespace App\Services;

/**
 * 任务详情页返回地址解析器。
 *
 * 详情页可能从任务大厅或三个“我的任务”页面进入。本类只允许项目内已知列表地址，
 * 防止用户通过 returnTo 参数把详情页返回按钮变成任意外部跳转链接。
 */
class TaskReturnNavigation
{
    /**
     * 允许作为任务详情来源的页面及其返回文案。
     *
     * @var array<string, string>
     */
    private const ALLOWED_PATHS = [
        '/tasks' => '返回任务大厅',
        '/my-tasks/published' => '返回我发布的',
        '/my-tasks/bids' => '返回我的投标',
        '/my-tasks/assignments' => '返回执行任务',
    ];

    /**
     * 解析详情页需要的返回导航信息。
     *
     * 参数缺失或不合法时统一回到任务大厅；合法地址会保留原查询条件和分页参数。
     *
     * @return array{activeNav: 'tasks'|'my-tasks', backLabel: string, backUrl: string, returnTo: string}
     */
    public function resolve(mixed $returnTo): array
    {
        $safeUrl = $this->safeUrl($returnTo) ?? '/tasks';
        $path = (string) parse_url($safeUrl, PHP_URL_PATH);

        return [
            'activeNav' => $path === '/tasks' ? 'tasks' : 'my-tasks',
            'backLabel' => self::ALLOWED_PATHS[$path],
            'backUrl' => $safeUrl,
            // 表单提交后继续把来源页带回详情页，避免投标或选标成功后丢失返回位置。
            'returnTo' => $safeUrl,
        ];
    }

    /**
     * 校验并返回允许的站内相对地址。
     *
     * 返回 null 表示调用方没有传入合法来源，重定向时不应附加该参数。
     */
    public function safeUrl(mixed $returnTo): ?string
    {
        if (! is_string($returnTo) || $returnTo === '' || strlen($returnTo) > 2048) {
            return null;
        }

        // 只接受单斜杠开头的站内地址；双斜杠可能被浏览器解释成外部域名。
        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return null;
        }

        // 控制字符不应出现在浏览器导航地址中。
        if (str_contains($returnTo, '\\') || preg_match('/[\x00-\x1F\x7F]/', $returnTo) === 1) {
            return null;
        }

        $parts = parse_url($returnTo);

        if ($parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            return null;
        }

        $path = $parts['path'] ?? '';

        return array_key_exists($path, self::ALLOWED_PATHS) ? $returnTo : null;
    }
}
