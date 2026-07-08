import { defineStore } from 'pinia';
import * as authApi from '../api/auth';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        checked: false,
    }),

    getters: {
        isAuthenticated: (state) => state.user !== null,
    },

    actions: {
        async fetchUser() {
            try {
                this.user = await authApi.me();
            } catch {
                this.user = null;
            } finally {
                this.checked = true;
            }
        },

        async login(email, password) {
            this.user = await authApi.login(email, password);
        },

        async logout() {
            await authApi.logout();
            this.user = null;
        },
    },
});
