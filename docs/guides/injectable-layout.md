# Injectable Layout — Hướng dẫn tích hợp cho nhiều service

> **Vấn đề:** Package `pkg-omnify-laravel-core` cung cấp các IAM pages (roles, users,
> assignments, ...) dùng chung cho nhiều service Laravel. Mỗi service có layout riêng
> (`AppLayout`, `AdminLayout`, ...). Nếu hardcode layout trong package, khi deploy sang
> service khác sẽ bị vỡ.
>
> **Giải pháp:** `IamLayoutContext` — host app inject layout của mình vào, package pages
> tự động dùng đúng layout mà không cần thay đổi code package.

---

## Mục lục

1. [Kiến trúc](#kiến-trúc)
2. [Quick start](#quick-start)
3. [API reference](#api-reference)
4. [Ví dụ triển khai](#ví-dụ-triển-khai)
5. [Layout type contract](#layout-type-contract)
6. [Fallback khi không configure](#fallback-khi-không-configure)
7. [Câu hỏi thường gặp](#câu-hỏi-thường-gặp)

---

## Kiến trúc

```
Host App (Service A, B, C...)
│
├── app.tsx
│   └── <IamLayoutContext.Provider value={YourLayout}>
│         <App {...props} />
│       </IamLayoutContext.Provider>
│
└── layouts/app-layout.tsx  ←── inject vào đây
        ↕
Package (@omnify-core)
├── contexts/iam-layout-context.tsx   ← context definition
└── pages/admin/iam/*.tsx             ← dùng useIamLayout()
        const Layout = useIamLayout()
        return <Layout breadcrumbs={[...]}>...</Layout>
```

Package **không import bất kỳ thứ gì** từ host app. Host app chủ động inject layout
thông qua React Context.

---

## Quick start

### 1. Cập nhật `resources/js/app.tsx` của service

```tsx
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import AppLayout from '@/layouts/app-layout';                           // layout của service
import { IamLayoutContext } from '@omnify-core/contexts/iam-layout-context'; // từ package

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, allPages),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <StrictMode>
                <IamLayoutContext.Provider value={AppLayout}>   {/* ← thêm dòng này */}
                    <App {...props} />
                </IamLayoutContext.Provider>
            </StrictMode>,
        );
    },
});
```

### 2. Đảm bảo `vite.config.ts` có alias `@omnify-core`

```ts
// vite.config.ts
import path from 'node:path';

export default defineConfig({
    resolve: {
        alias: {
            '@omnify-core': path.resolve(__dirname, 'packages/pkg-omnify-laravel-core/resources/js'),
        },
    },
});
```

### 3. Đảm bảo package pages được resolved

```ts
// app.tsx — remapping package pages về namespace của host
const packagePages = import.meta.glob(
    '../../packages/pkg-omnify-laravel-core/resources/js/pages/**/*.tsx',
);
const remappedPackagePages: typeof hostPages = {};
for (const [key, value] of Object.entries(packagePages)) {
    const remapped = key.replace(
        '../../packages/pkg-omnify-laravel-core/resources/js/pages/',
        './pages/',
    );
    remappedPackagePages[remapped] = value;
}
const allPages = { ...remappedPackagePages, ...hostPages };
```

---

## API reference

### `IamLayoutContext`

```ts
import { IamLayoutContext } from '@omnify-core/contexts/iam-layout-context';
```

React context chứa layout component. Default value là `PassthroughLayout` (xem
[Fallback](#fallback-khi-không-configure)).

### `useIamLayout()`

```ts
import { useIamLayout } from '@omnify-core/contexts/iam-layout-context';

// Trong React component:
const Layout = useIamLayout();
return <Layout breadcrumbs={[...]}>...</Layout>;
```

Hook trả về layout component hiện tại từ context. Luôn trả về một component hợp lệ
(không bao giờ `null` hay `undefined`).

### `IamLayoutComponent` type

```ts
import type { IamLayoutComponent } from '@omnify-core/contexts/iam-layout-context';
```

Type của layout component mà package pages yêu cầu:

```ts
type IamLayoutComponent = ComponentType<{
    children: ReactNode;
    breadcrumbs?: IamBreadcrumbItem[];
}>;
```

### `IamBreadcrumbItem` type

```ts
import type { IamBreadcrumbItem } from '@omnify-core/contexts/iam-layout-context';

// Shape:
type IamBreadcrumbItem = {
    title: string;
    href: string;
};
```

---

## Ví dụ triển khai

### Service A — Task App (layout phức tạp với sidebar)

```tsx
// resources/js/app.tsx
import AppLayout from '@/layouts/app-layout';           // sidebar + header layout
import { IamLayoutContext } from '@omnify-core/contexts/iam-layout-context';

root.render(
    <StrictMode>
        <IamLayoutContext.Provider value={AppLayout}>
            <App {...props} />
        </IamLayoutContext.Provider>
    </StrictMode>,
);
```

### Service B — Console App (layout admin đơn giản)

```tsx
// resources/js/app.tsx
import AdminLayout from '@/layouts/admin-layout';       // layout khác tên
import { IamLayoutContext } from '@omnify-core/contexts/iam-layout-context';

root.render(
    <StrictMode>
        <IamLayoutContext.Provider value={AdminLayout}>
            <App {...props} />
        </IamLayoutContext.Provider>
    </StrictMode>,
);
```

### Service C — Không muốn dùng layout wrapping

Không set `IamLayoutContext.Provider` → tự động dùng `PassthroughLayout` (render
children trực tiếp, không có frame).

---

## Layout type contract

Layout của host app **phải** accept ít nhất hai props sau:

| Prop | Type | Bắt buộc | Mô tả |
|------|------|----------|-------|
| `children` | `ReactNode` | ✅ | Nội dung trang |
| `breadcrumbs` | `IamBreadcrumbItem[]` | ❌ | Đường dẫn breadcrumb |

Ví dụ layout hợp lệ tối thiểu:

```tsx
export default function AppLayout({
    children,
    breadcrumbs,
}: {
    children: ReactNode;
    breadcrumbs?: { title: string; href: string }[];
}) {
    return (
        <div>
            <Breadcrumbs items={breadcrumbs} />
            <main>{children}</main>
        </div>
    );
}
```

TypeScript sẽ báo lỗi tại compile time nếu layout không thỏa mãn `IamLayoutComponent`.

---

## Fallback khi không configure

Nếu host app **không** bọc bằng `IamLayoutContext.Provider`, package sử dụng
`PassthroughLayout` — một component render children trực tiếp không có wrapping:

```tsx
function PassthroughLayout({
    children,
}: {
    children: ReactNode;
    breadcrumbs?: IamBreadcrumbItem[];
}) {
    return <>{children}</>;
}
```

Điều này có nghĩa:
- IAM pages vẫn render được (không crash)
- Không có sidebar, header, breadcrumb
- Hữu ích khi testing, Storybook, hoặc embed IAM vào modal

---

## Câu hỏi thường gặp

**Q: Layout của tôi có thêm nhiều props khác, có bị lỗi TypeScript không?**

Không. TypeScript structural typing cho phép layout có thêm props tùy ý, miễn là nó
accept `children` và `breadcrumbs?`.

**Q: Tôi muốn IAM pages dùng layout KHÁC với các trang khác của service.**

Tạo một `IamLayout` wrapper riêng và inject vào:

```tsx
// layouts/iam-layout.tsx
import AppLayout from '@/layouts/app-layout';
import type { IamBreadcrumbItem } from '@omnify-core/contexts/iam-layout-context';

export default function IamLayout({
    children,
    breadcrumbs,
}: {
    children: ReactNode;
    breadcrumbs?: IamBreadcrumbItem[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="iam-container">{children}</div>
        </AppLayout>
    );
}

// app.tsx
import IamLayout from '@/layouts/iam-layout';
<IamLayoutContext.Provider value={IamLayout}>
```

**Q: Breadcrumb items được định nghĩa ở đâu?**

Mỗi IAM page tự định nghĩa breadcrumbs của mình inline và truyền vào `Layout`:

```tsx
// Trong package page (không cần chỉnh)
<Layout breadcrumbs={[
    { title: 'IAM', href: '/admin/iam' },
    { title: 'Assignments', href: '/admin/iam/assignments' },
]}>
```

Host app chỉ cần render `breadcrumbs` prop trong layout của mình.

**Q: Package này có thể dùng với Storybook không?**

Có. Trong Storybook decorator, wrap component với `IamLayoutContext.Provider` và inject
một mock layout.

---

## File liên quan

| File | Mô tả |
|------|-------|
| `packages/pkg-omnify-laravel-core/resources/js/contexts/iam-layout-context.tsx` | Context, hook, và type definitions |
| `packages/pkg-omnify-laravel-core/resources/js/pages/admin/iam/*.tsx` | 12 IAM pages dùng `useIamLayout()` |
| `resources/js/app.tsx` | Host app inject `AppLayout` vào context |
