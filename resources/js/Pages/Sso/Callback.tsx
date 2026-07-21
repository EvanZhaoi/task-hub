import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { csrfToken } from '@/utils/csrf';
import { currentSearchParams } from '@/utils/url';

type CallbackState = 'processing' | 'failed';

// 回调页只需要两个状态：处理中和失败。
// 成功时不会停留在当前页面，而是立即 window.location.replace 到业务页面。
function readAuthParams(): URLSearchParams {
    // 公司 SSO 已确认使用 query string 回调，例如：
    // /sso/callback?access_token=xxx
    // 因此这里读取 window.location.search，而不是 window.location.hash。
    return currentSearchParams();
}

export default function SsoCallback() {
    // processing 用于展示“正在建立本地会话”，failed 用于展示错误和重新登录入口。
    const [callbackState, setCallbackState] = useState<CallbackState>('processing');
    const [message, setMessage] = useState('正在完成单点登录，请稍候。');

    useEffect(() => {
        // 登录收尾属于副作用，需要放在 useEffect 中，避免 React 重新渲染时重复提交。
        // 这个页面只负责把公司 SSO 回调带回来的 access_token 交给 Laravel。
        const params = readAuthParams();
        const accessToken = params.get('access_token');

        if (!accessToken) {
            // 没有 token 时不能继续创建本地 Session，必须让用户重新走登录流程。
            setCallbackState('failed');
            setMessage('SSO 回调缺少 access_token。');
            return;
        }

        // 前端只把 accessToken 交给 Laravel。
        // 当前用户身份、工号、角色都由后端调用公司接口和本地角色表确定。
        fetch('/sso/session', {
            method: 'POST',
            headers: {
                // 告诉 Laravel：前端希望后端返回 JSON，而不是重定向或 HTML 错误页。
                Accept: 'application/json',
                'Content-Type': 'application/json',
                // Laravel web 路由默认开启 CSRF 校验，POST 请求必须带这个头。
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                // 只传 accessToken；用户姓名、部门、角色都由 Laravel 后端决定。
                accessToken,
            }),
        })
            .then(async (response) => {
                const payload = (await response.json()) as { redirectTo?: string; message?: string };

                if (!response.ok) {
                    throw new Error(payload.message ?? 'SSO 登录失败。');
                }

                // 后端建立 Session 成功后返回 redirectTo。
                // replace 会替换当前回调页历史记录，避免浏览器后退回到带 token 的 URL。
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
            <Card as="section" className="w-full max-w-md border-[#ebebeb] shadow-sm">
                <CardContent className="p-6">
                    {/* 这里不是完整登录页，只是 SSO 回调过程中的中间状态提示。 */}
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
                        <Button asChild className="mt-5">
                            <a href="/login">重新登录</a>
                        </Button>
                    ) : null}
                </CardContent>
            </Card>
        </main>
    );
}
