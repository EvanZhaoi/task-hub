import { useEffect, useState } from 'react';

type CallbackState = 'processing' | 'failed';

function readAuthParams(): URLSearchParams {
    const hash = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : window.location.hash;

    if (hash !== '') {
        return new URLSearchParams(hash);
    }

    return new URLSearchParams(window.location.search);
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function SsoCallback() {
    const [state, setState] = useState<CallbackState>('processing');
    const [message, setMessage] = useState('正在完成单点登录，请稍候。');

    useEffect(() => {
        const params = readAuthParams();
        const accessToken = params.get('access_token');
        const ssoState = params.get('state');

        if (!accessToken || !ssoState) {
            setState('failed');
            setMessage('SSO 回调缺少 access_token 或 state。');
            return;
        }

        fetch('/sso/session', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                accessToken,
                state: ssoState,
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
                setState('failed');
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
                            {state === 'processing' ? '正在建立本地会话' : '登录未完成'}
                        </p>
                    </div>
                </div>

                <p className="text-sm leading-6 text-[#6e6e80]">{message}</p>

                {state === 'failed' ? (
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
