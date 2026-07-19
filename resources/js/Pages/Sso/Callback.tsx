import { useEffect, useState } from 'react';

type CallbackState = 'processing' | 'failed';

function readAuthParams(): URLSearchParams {
    // 隐式模式通常把 access_token 放在 URL fragment 中，例如：
    // /sso/callback#access_token=xxx
    // fragment 不会发送给 Laravel，只能在浏览器中通过 window.location.hash 读取。
    const hash = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : window.location.hash;

    if (hash !== '') {
        return new URLSearchParams(hash);
    }

    // 保留 query string 兜底，兼容公司 SSO 可能把参数放到 ?access_token=xxx 的实现。
    return new URLSearchParams(window.location.search);
}

function csrfToken(): string {
    // /sso/session 是 Laravel web 路由，POST 请求需要 CSRF token。
    // token 来自 resources/views/app.blade.php 中的 csrf meta。
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function SsoCallback() {
    const [callbackState, setCallbackState] = useState<CallbackState>('processing');
    const [message, setMessage] = useState('正在完成单点登录，请稍候。');

    useEffect(() => {
        // 登录收尾属于副作用，需要放在 useEffect 中，避免 React 重新渲染时重复提交。
        const params = readAuthParams();
        const accessToken = params.get('access_token');

        if (!accessToken) {
            setCallbackState('failed');
            setMessage('SSO 回调缺少 access_token。');
            return;
        }

        // 前端只把 accessToken 交给 Laravel。
        // 当前用户身份、工号、角色都由后端调用公司接口和本地角色表确定。
        fetch('/sso/session', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                accessToken,
            }),
        })
            .then(async (response) => {
                const payload = (await response.json()) as { redirectTo?: string; message?: string };

                if (!response.ok) {
                    throw new Error(payload.message ?? 'SSO 登录失败。');
                }

                window.location.replace(payload.redirectTo ?? '/tasks');
            })
            .catch((error: unknown) => {
                // 失败时停留在回调页，给用户一个明确的重新登录入口。
                setCallbackState('failed');
                setMessage(error instanceof Error ? error.message : 'SSO 登录失败。');
            });
    }, []);

    return (
        <main className="flex min-h-screen items-center justify-center bg-[#fafafa] px-6 text-[#1a1a1a]">
            <section className="w-full max-w-md rounded-lg border border-[#ebebeb] bg-white p-6 shadow-sm">
                <div className="mb-4 flex items-center gap-3">
                    <div className="flex size-8 items-center justify-center rounded-md bg-[#5e6ad2] text-sm font-bold text-white">
                        T
                    </div>
                    <div>
                        <h1 className="m-0 text-lg font-semibold">TaskHub SSO</h1>
                        <p className="mt-1 text-sm text-[#6e6e80]">
                            {callbackState === 'processing' ? '正在建立本地会话' : '登录未完成'}
                        </p>
                    </div>
                </div>

                <p className="text-sm leading-6 text-[#6e6e80]">{message}</p>

                {callbackState === 'failed' ? (
                    <a
                        className="mt-5 inline-flex rounded-md bg-[#5e6ad2] px-4 py-2 text-sm font-medium text-white"
                        href="/login"
                    >
                        重新登录
                    </a>
                ) : null}
            </section>
        </main>
    );
}
