import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/',
        component: () => import('../components/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('../views/DashboardView.vue'),
            },
            {
                path: 'keywords',
                name: 'keywords',
                component: () => import('../views/KeywordListView.vue'),
            },
            {
                path: 'keywords/new',
                name: 'keywords.create',
                component: () => import('../views/KeywordFormView.vue'),
            },
            {
                path: 'keywords/:id/edit',
                name: 'keywords.edit',
                component: () => import('../views/KeywordFormView.vue'),
                props: true,
            },
            {
                path: 'posts',
                name: 'posts',
                component: () => import('../views/PostListView.vue'),
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.checked) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
