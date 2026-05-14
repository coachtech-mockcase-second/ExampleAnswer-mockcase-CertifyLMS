/**
 * CSRF + JSON 共通 fetch ラッパー。
 * Sanctum SPA Cookie / Fortify session 両対応。
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function send(method, url, data = null, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers ?? {}),
    };

    let body;
    if (data instanceof FormData) {
        body = data;
    } else if (data != null) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(data);
    }

    const response = await fetch(url, {
        method,
        headers,
        body,
        credentials: 'same-origin',
        ...options,
    });

    const contentType = response.headers.get('content-type') ?? '';
    const isJson = contentType.includes('application/json');
    const payload = isJson ? await response.json() : await response.text();

    if (!response.ok) {
        const error = new Error(`HTTP ${response.status}`);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

export const getJson = (url, options) => send('GET', url, null, options);
export const postJson = (url, data, options) => send('POST', url, data, options);
export const putJson = (url, data, options) => send('PUT', url, data, options);
export const patchJson = (url, data, options) => send('PATCH', url, data, options);
export const deleteJson = (url, options) => send('DELETE', url, null, options);
