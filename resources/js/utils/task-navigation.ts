/**
 * 生成带安全返回上下文的任务详情地址。
 *
 * 浏览器当前路径和查询条件会作为 returnTo 交给后端白名单校验，详情页因此可以精确返回
 * 原列表的关键词、状态和页码，而不是一律回到任务大厅第一页。
 */
export function taskDetailUrl(taskId: string): string {
    if (typeof window === 'undefined') {
        return `/tasks/${taskId}`;
    }

    const returnTo = `${window.location.pathname}${window.location.search}`;
    const query = new URLSearchParams({ returnTo });

    return `/tasks/${taskId}?${query.toString()}`;
}
