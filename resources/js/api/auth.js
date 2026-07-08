import client, { ensureCsrfCookie } from './client';

export async function login(email, password) {
    await ensureCsrfCookie();
    const response = await client.post('/login', { email, password });
    return response.data.data;
}

export async function logout() {
    await client.post('/logout');
}

export async function me() {
    const response = await client.get('/me');
    return response.data.data;
}
