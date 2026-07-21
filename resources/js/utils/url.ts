type QueryValue = number | string | null | undefined;

export function currentSearchParams(): URLSearchParams {
    // 统一读取当前页面 query string，避免页面里直接依赖 window.location.search。
    return new URLSearchParams(window.location.search);
}

export function urlWithQuery(path: string, params: Record<string, QueryValue>): string {
    // 只拼接有值的查询参数，null/undefined/空字符串都表示不进入 URL。
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        query.set(key, String(value));
    });

    const queryString = query.toString();

    return queryString === '' ? path : `${path}?${queryString}`;
}
