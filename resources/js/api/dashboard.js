import client from './client';

export async function getDashboard() {
    const response = await client.get('/api/dashboard');
    return response.data.data;
}
