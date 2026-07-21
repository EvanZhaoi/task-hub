type QueryValue = number | string | null | undefined;

export function currentSearchParams(): URLSearchParams {
    return new URLSearchParams(window.location.search);
}

export function urlWithQuery(path: string, params: Record<string, QueryValue>): string {
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
