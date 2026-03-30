export const DATASET_ENTRY_DRAG_TYPE = 'application/x-blockforge-dataset-entry'

let activeEntryDragPayload = null
let dragPreview = null
let transparentDragImage = null

function createPreviewNode(tagName, styles = {}, textContent = '') {
    const node = document.createElement(tagName)
    Object.assign(node.style, styles)

    if (textContent !== '') {
        node.textContent = textContent
    }

    return node
}

function createTransparentDragImage() {
    if (transparentDragImage) {
        return transparentDragImage
    }

    const canvas = document.createElement('canvas')
    canvas.width = 1
    canvas.height = 1
    transparentDragImage = canvas

    return transparentDragImage
}

function buildEntryDragPayload(entry) {
    return {
        entryId: Number(entry?.id ?? 0),
        title: String(entry?.title ?? entry?.slug ?? 'Entry'),
        categoryIds: Array.isArray(entry?.categories)
            ? entry.categories
                .map((category) => Number(category?.id))
                .filter((id) => Number.isInteger(id) && id > 0)
            : [],
    }
}

function buildEntryDragPreview(payload) {
    const preview = createPreviewNode('div', {
        position: 'fixed',
        left: '0',
        top: '0',
        padding: '8px 10px',
        background: 'linear-gradient(180deg, rgba(24, 24, 31, 0.96) 0%, rgba(16, 18, 24, 0.96) 100%)',
        backdropFilter: 'blur(14px)',
        color: 'rgba(241,245,249,0.96)',
        fontSize: '12px',
        fontFamily: 'var(--bf-font-sans)',
        fontWeight: '600',
        lineHeight: '1',
        borderRadius: '16px',
        border: '1px solid rgba(148, 163, 184, 0.18)',
        pointerEvents: 'none',
        boxShadow: '0 18px 40px rgba(2, 6, 23, 0.28), 0 4px 12px rgba(2, 6, 23, 0.18), 0 0 0 1px rgba(255,255,255,0.04) inset',
        display: 'flex',
        flexDirection: 'column',
        gap: '4px',
        zIndex: '9999',
        transform: 'translate3d(0,0,0)',
        minWidth: '168px',
        maxWidth: '240px',
    })

    const label = createPreviewNode('span', {
        display: 'block',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    }, payload.title || 'Entry')

    const meta = createPreviewNode('span', {
        color: 'rgba(148, 163, 184, 0.82)',
        fontSize: '10px',
        fontWeight: '600',
        letterSpacing: '0.01em',
    }, 'Drop onto a category to assign')

    preview.append(label, meta)

    return preview
}

function updateEntryDragPreviewPosition(event) {
    if (!dragPreview) {
        return
    }

    dragPreview.style.transform = `translate3d(${event.clientX + 14}px, ${event.clientY + 14}px, 0)`
}

function handleDocumentDragOver(event) {
    updateEntryDragPreviewPosition(event)
}

export function startDatasetEntryDragPreview(event, entry) {
    clearDatasetEntryDragPreview()

    if (typeof document === 'undefined') {
        return
    }

    const payload = buildEntryDragPayload(entry)
    dragPreview = buildEntryDragPreview(payload)
    document.body.appendChild(dragPreview)
    updateEntryDragPreviewPosition(event)
    document.addEventListener('dragover', handleDocumentDragOver, true)

    try {
        event.dataTransfer?.setDragImage?.(createTransparentDragImage(), 0, 0)
    } catch {
        // Ignore environments that reject custom drag images.
    }
}

export function writeDatasetEntryDragData(dataTransfer, entry) {
    if (!dataTransfer || !entry) {
        return
    }

    const payload = buildEntryDragPayload(entry)

    if (!Number.isInteger(payload.entryId) || payload.entryId <= 0) {
        return
    }

    activeEntryDragPayload = payload
    dataTransfer.effectAllowed = 'copy'

    try {
        dataTransfer.setData(DATASET_ENTRY_DRAG_TYPE, JSON.stringify(payload))
        dataTransfer.setData('text/plain', `bf-dataset-entry:${payload.entryId}`)
    } catch {
        // Some environments restrict drag payloads; keep the drag alive without crashing.
    }
}

export function hasDatasetEntryDragData(dataTransfer) {
    if (activeEntryDragPayload !== null) {
        return true
    }

    if (!dataTransfer) {
        return false
    }

    const types = Array.from(dataTransfer.types ?? [])

    return types.includes(DATASET_ENTRY_DRAG_TYPE)
        || types.includes('text/plain')
}

export function readDatasetEntryDragData(dataTransfer) {
    if (activeEntryDragPayload !== null) {
        return activeEntryDragPayload
    }

    if (!dataTransfer) {
        return null
    }

    try {
        const rawCustom = dataTransfer.getData(DATASET_ENTRY_DRAG_TYPE)

        if (rawCustom) {
            const parsed = JSON.parse(rawCustom)
            const entryId = Number(parsed?.entryId)

            if (Number.isInteger(entryId) && entryId > 0) {
                return {
                    entryId,
                    categoryIds: Array.isArray(parsed?.categoryIds)
                        ? parsed.categoryIds
                            .map((id) => Number(id))
                            .filter((id) => Number.isInteger(id) && id > 0)
                        : [],
                }
            }
        }

        const rawText = dataTransfer.getData('text/plain')
        const fallbackMatch = /^bf-dataset-entry:(\d+)$/.exec(rawText)

        if (!fallbackMatch) {
            return null
        }

        return {
            entryId: parseInt(fallbackMatch[1], 10),
            categoryIds: [],
        }
    } catch {
        return null
    }
}

export function clearDatasetEntryDragData() {
    activeEntryDragPayload = null
    clearDatasetEntryDragPreview()
}

export function clearDatasetEntryDragPreview() {
    document.removeEventListener('dragover', handleDocumentDragOver, true)

    if (dragPreview) {
        dragPreview.remove()
        dragPreview = null
    }
}
