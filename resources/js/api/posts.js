import client from './client';

export async function listPosts(filters = {}) {
    const response = await client.get('/api/posts', { params: filters });
    return response.data;
}

export async function getPost(id) {
    const response = await client.get(`/api/posts/${id}`);
    return response.data.data;
}
