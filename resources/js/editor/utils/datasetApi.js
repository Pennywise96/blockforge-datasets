import { csrfHeaders, jsonHeaders, request, requestJson } from '@blockforge-cms/editor-sdk'

export async function fetchDatasetTypes() {
    return await requestJson('/api/cms/datasets/types')
}

export async function fetchDatasetPageDetailSettings(pageId) {
    return await requestJson(`/api/cms/datasets/pages/${pageId}/detail-settings`)
}

export async function updateDatasetPageDetailSettings(pageId, payload) {
    return await requestJson(`/api/cms/datasets/pages/${pageId}/detail-settings`, {
        method: 'PUT',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    })
}

export async function createDatasetType(payload) {
    return await requestJson('/api/cms/datasets/types', {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    })
}

export async function updateDatasetType(typeId, payload) {
    return await requestJson(`/api/cms/datasets/types/${typeId}`, {
        method: 'PUT',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    })
}

export async function deleteDatasetType(typeId) {
    await request(`/api/cms/datasets/types/${typeId}`, {
        method: 'DELETE',
        headers: csrfHeaders(),
    })
}

export async function fetchDatasetCategories(typeId) {
    return await requestJson(`/api/cms/datasets/types/${typeId}/categories`)
}

export async function createDatasetCategory(typeId, payload) {
    return await requestJson(`/api/cms/datasets/types/${typeId}/categories`, {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    })
}

export async function updateDatasetCategory(categoryId, payload) {
    return await requestJson(`/api/cms/datasets/categories/${categoryId}`, {
        method: 'PUT',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    })
}

export async function deleteDatasetCategory(categoryId) {
    await request(`/api/cms/datasets/categories/${categoryId}`, {
        method: 'DELETE',
        headers: csrfHeaders(),
    })
}

export async function fetchDatasets({ typeCode, categorySlug = null, visibility = 'all', page = 1, perPage = 30 }) {
    const params = new URLSearchParams({
        type: String(typeCode),
        per_page: String(perPage),
        page: String(page),
    })

    if (categorySlug) {
        params.set('category', String(categorySlug))
    }

    if (visibility !== 'all') {
        params.set('visibility', String(visibility))
    }

    return await requestJson(`/api/cms/datasets?${params}`)
}

export async function createDataset(payload) {
    return await requestJson('/api/cms/datasets', {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    })
}

export async function updateDataset(datasetId, payload) {
    return await requestJson(`/api/cms/datasets/${datasetId}`, {
        method: 'PUT',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    })
}

export async function updateDatasetTranslation(datasetId, locale, payload) {
    return await requestJson(`/api/cms/datasets/${datasetId}/translations/${locale}`, {
        method: 'PUT',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    })
}

export async function syncDatasetCategories(datasetId, categoryIds) {
    return await requestJson(`/api/cms/datasets/${datasetId}/categories`, {
        method: 'PUT',
        headers: jsonHeaders(),
        body: JSON.stringify({
            category_ids: categoryIds,
        }),
    })
}

export async function deleteDataset(datasetId) {
    await request(`/api/cms/datasets/${datasetId}`, {
        method: 'DELETE',
        headers: csrfHeaders(),
    })
}
