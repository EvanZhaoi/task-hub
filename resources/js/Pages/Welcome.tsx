type WelcomeProps = {
    // appName 来自 routes/web.php 中 Inertia::render('Welcome', ['appName' => ...])。
    appName: string;
};

export default function Welcome({ appName }: WelcomeProps) {
    return (
        // Welcome 只是应用壳验证页，不承载 TaskHub 业务功能。
        <main className="min-h-screen bg-zinc-950 text-zinc-100">
            <section className="mx-auto flex min-h-screen max-w-5xl flex-col justify-center px-6 py-16">
                <p className="text-sm font-medium uppercase tracking-[0.24em] text-cyan-300">Phase 1</p>
                <h1 className="mt-4 text-4xl font-semibold tracking-normal sm:text-5xl">{appName}</h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-zinc-300">
                    Laravel 13 monolith with React, TypeScript, Inertia.js, MySQL 8, and Pest.
                    This screen only verifies the application shell.
                </p>
            </section>
        </main>
    );
}
