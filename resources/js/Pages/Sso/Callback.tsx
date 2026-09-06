import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type SsoCallbackProps = {
    // status 由 Laravel SsoController::callback 传入。
    // 授权码模式成功时后端会直接 redirect，不会停留在这个页面。
    status?: 'failed';
    // message 是后端给出的失败原因，例如缺少 code 或 token 接口请求失败。
    message?: string;
};

export default function SsoCallback({ message, status = 'failed' }: SsoCallbackProps) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-[#fafafa] px-6 text-[#1a1a1a]">
            <Card as="section" className="w-full max-w-md border-[#ebebeb] shadow-sm">
                <CardContent className="p-6">
                    {/* 这里不是完整登录页，只是 SSO 授权码回调失败时的提示页。 */}
                    <div className="mb-4 flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-[#5e6ad2] text-sm font-bold text-white">
                            T
                        </div>
                        <div>
                            <h1 className="m-0 text-lg font-semibold">TaskHub SSO</h1>
                            <p className="mt-1 text-sm text-[#6e6e80]">
                                {status === 'failed' ? '登录未完成' : '正在完成单点登录'}
                            </p>
                        </div>
                    </div>

                    <p className="text-sm leading-6 text-[#6e6e80]">{message ?? 'SSO 授权码登录失败。'}</p>

                    <Button asChild className="mt-5">
                        <a href="/login">重新登录</a>
                    </Button>
                </CardContent>
            </Card>
        </main>
    );
}
