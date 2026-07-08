import axios from 'axios';

const client = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
    },
});

let csrfInitialized = false;

/**
 * Sanctum SPA cookie 認證：呼叫受保護端點前，須先取得 CSRF cookie 一次。
 */
export async function ensureCsrfCookie() {
    if (csrfInitialized) {
        return;
    }

    await client.get('/sanctum/csrf-cookie');
    csrfInitialized = true;
}

export default client;
