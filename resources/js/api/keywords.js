import client from './client';

export async function listKeywords(page = 1) {
    const response = await client.get('/api/keywords', { params: { page } });
    return response.data;
}

export async function getKeyword(id) {
    const response = await client.get(`/api/keywords/${id}`);
    return response.data.data;
}

export async function createKeyword(payload) {
    const response = await client.post('/api/keywords', payload);
    return response.data.data;
}

export async function updateKeyword(id, payload) {
    const response = await client.put(`/api/keywords/${id}`, payload);
    return response.data.data;
}

export async function deleteKeyword(id) {
    await client.delete(`/api/keywords/${id}`);
}

export async function crawlKeywordNow(id) {
    const response = await client.post(`/api/keywords/${id}/crawl-now`);
    return response.data;
}
