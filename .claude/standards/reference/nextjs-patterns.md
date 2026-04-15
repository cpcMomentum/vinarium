# Next.js Patterns

Best-Practice Patterns für Next.js 15+ Frontend-Entwicklung.

> **Referenz:** Ergänzt `.claude/standards/code-style-linting.md` mit Next.js-spezifischen Patterns.

---

## 1. Projekt-Struktur (App Router)

```
frontend/
├── app/
│   ├── (auth)/              # Route Group (kein URL-Segment)
│   │   ├── login/
│   │   │   └── page.tsx
│   │   └── register/
│   │       └── page.tsx
│   ├── (dashboard)/
│   │   ├── layout.tsx       # Shared Layout
│   │   ├── page.tsx         # /dashboard
│   │   └── settings/
│   │       └── page.tsx     # /dashboard/settings
│   ├── api/                 # API Routes
│   │   └── [...route]/
│   │       └── route.ts
│   ├── layout.tsx           # Root Layout
│   ├── page.tsx             # Home
│   ├── loading.tsx          # Loading UI
│   ├── error.tsx            # Error UI
│   └── not-found.tsx        # 404
├── components/
│   ├── ui/                  # shadcn/ui Komponenten
│   ├── forms/               # Form-Komponenten
│   └── layout/              # Layout-Komponenten
├── lib/
│   ├── api.ts               # API Client
│   ├── utils.ts             # Utilities
│   └── validations.ts       # Zod Schemas
├── hooks/                   # Custom Hooks
├── types/                   # TypeScript Types
├── public/                  # Static Assets
└── styles/
    └── globals.css          # Tailwind
```

---

## 2. Server vs. Client Components

### 2.1 Server Components (Default)

```tsx
// app/users/page.tsx
// Server Component - kein "use client"

import { getUsers } from '@/lib/api';

export default async function UsersPage() {
  const users = await getUsers(); // Direkt auf Server

  return (
    <div>
      {users.map(user => (
        <UserCard key={user.id} user={user} />
      ))}
    </div>
  );
}
```

### 2.2 Client Components

```tsx
// components/forms/LoginForm.tsx
'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export function LoginForm() {
  const [email, setEmail] = useState('');
  const router = useRouter();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    // Login Logic
    router.push('/dashboard');
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* Form Fields */}
    </form>
  );
}
```

### 2.3 Wann Client Components nutzen

- Event Handler (onClick, onChange)
- React Hooks (useState, useEffect)
- Browser APIs (localStorage, window)
- Interaktive UI (Modals, Dropdowns)

---

## 3. Data Fetching Patterns

### 3.1 Server-Side Fetching

```tsx
// app/posts/[id]/page.tsx
async function getPost(id: string) {
  const res = await fetch(`${API_URL}/posts/${id}`, {
    next: { revalidate: 60 }, // ISR: 60 Sekunden
  });

  if (!res.ok) {
    throw new Error('Failed to fetch post');
  }

  return res.json();
}

export default async function PostPage({
  params,
}: {
  params: { id: string };
}) {
  const post = await getPost(params.id);

  return <PostContent post={post} />;
}
```

### 3.2 Client-Side mit TanStack Query

```tsx
// hooks/useUsers.ts
'use client';

import { useQuery } from '@tanstack/react-query';
import { getUsers } from '@/lib/api';

export function useUsers() {
  return useQuery({
    queryKey: ['users'],
    queryFn: getUsers,
  });
}

// components/UserList.tsx
'use client';

import { useUsers } from '@/hooks/useUsers';

export function UserList() {
  const { data: users, isLoading, error } = useUsers();

  if (isLoading) return <Skeleton />;
  if (error) return <ErrorMessage error={error} />;

  return (
    <ul>
      {users?.map(user => (
        <li key={user.id}>{user.name}</li>
      ))}
    </ul>
  );
}
```

### 3.3 Server Actions

```tsx
// app/actions.ts
'use server';

import { revalidatePath } from 'next/cache';

export async function createPost(formData: FormData) {
  const title = formData.get('title');
  const content = formData.get('content');

  await db.post.create({
    data: { title, content },
  });

  revalidatePath('/posts');
}

// In Component
<form action={createPost}>
  <input name="title" />
  <textarea name="content" />
  <button type="submit">Create</button>
</form>
```

---

## 4. Layout Patterns

### 4.1 Root Layout

```tsx
// app/layout.tsx
import { Inter } from 'next/font/google';
import { Providers } from '@/components/providers';
import './globals.css';

const inter = Inter({ subsets: ['latin'] });

export const metadata = {
  title: 'My App',
  description: 'Description',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="de">
      <body className={inter.className}>
        <Providers>
          {children}
        </Providers>
      </body>
    </html>
  );
}
```

### 4.2 Nested Layouts

```tsx
// app/(dashboard)/layout.tsx
import { Sidebar } from '@/components/layout/Sidebar';
import { Header } from '@/components/layout/Header';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen">
      <Sidebar />
      <div className="flex-1">
        <Header />
        <main className="p-6">{children}</main>
      </div>
    </div>
  );
}
```

---

## 5. State Management

### 5.1 Entscheidungsmatrix

| State-Typ | Lösung |
|-----------|--------|
| Server Data | TanStack Query |
| Form State | react-hook-form / useState |
| URL State | useSearchParams, usePathname |
| Local UI State | useState, useReducer |
| Shared UI State | Zustand / Context |

### 5.2 Zustand für globalen State

```tsx
// store/user.ts
import { create } from 'zustand';

interface UserState {
  user: User | null;
  setUser: (user: User | null) => void;
}

export const useUserStore = create<UserState>((set) => ({
  user: null,
  setUser: (user) => set({ user }),
}));
```

---

## 6. Form Patterns

### 6.1 react-hook-form + Zod

```tsx
'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

const schema = z.object({
  email: z.string().email('Ungültige E-Mail'),
  password: z.string().min(8, 'Mindestens 8 Zeichen'),
});

type FormData = z.infer<typeof schema>;

export function LoginForm() {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  const onSubmit = async (data: FormData) => {
    // Submit Logic
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <input {...register('email')} />
      {errors.email && <span>{errors.email.message}</span>}

      <input type="password" {...register('password')} />
      {errors.password && <span>{errors.password.message}</span>}

      <button type="submit" disabled={isSubmitting}>
        {isSubmitting ? 'Loading...' : 'Login'}
      </button>
    </form>
  );
}
```

---

## 7. Error Handling

### 7.1 Error Boundary

```tsx
// app/error.tsx
'use client';

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="flex flex-col items-center justify-center min-h-screen">
      <h2>Etwas ist schiefgelaufen!</h2>
      <button onClick={() => reset()}>Erneut versuchen</button>
    </div>
  );
}
```

### 7.2 Loading State

```tsx
// app/users/loading.tsx
import { Skeleton } from '@/components/ui/skeleton';

export default function Loading() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-12 w-full" />
      <Skeleton className="h-12 w-full" />
      <Skeleton className="h-12 w-full" />
    </div>
  );
}
```

---

## 8. Performance Patterns

### 8.1 Image Optimization

```tsx
import Image from 'next/image';

<Image
  src="/hero.jpg"
  alt="Hero Image"
  width={1200}
  height={600}
  priority // Für Above-the-fold
  placeholder="blur"
  blurDataURL={blurDataUrl}
/>
```

### 8.2 Dynamic Imports

```tsx
import dynamic from 'next/dynamic';

const HeavyComponent = dynamic(() => import('./HeavyComponent'), {
  loading: () => <Skeleton />,
  ssr: false, // Nur Client-Side
});
```

### 8.3 Suspense Boundaries

```tsx
import { Suspense } from 'react';

export default function Page() {
  return (
    <div>
      <h1>Dashboard</h1>
      <Suspense fallback={<ChartSkeleton />}>
        <AsyncChart />
      </Suspense>
    </div>
  );
}
```

---

## Referenzen

- `.claude/standards/code-style-linting.md` - ESLint/Prettier
- `.claude/standards/api-design.md` - API Integration
- `techstack.md` - Tech Stack Spezifikation
