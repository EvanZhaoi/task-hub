# TaskHub Frontend

当前前端已清空，旧版 mock 页面、组件、store 和路由不再保留。

下一版从零重写，技术口径：

- React 19
- TypeScript
- Vite
- Tailwind CSS v4
- React Router 7
- Zustand 5
- shadcn/ui + Radix UI

## Commands

```bash
npm run dev
npm run build
npm run lint
```

## Rewrite Notes

旧实现删除后，仅保留一个最小可启动入口：

- `src/main.tsx`
- `src/styles/globals.css`

重写时以 `docs/prototype/` 作为视觉参考，以 `docs/模块与数据库设计.md` 作为 API 与数据结构参考。
