export function csrfToken(): string {
    // Laravel web 路由的 POST 请求需要读取 Blade meta 中的 CSRF token。
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
