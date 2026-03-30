/**
 * Generic recursive tree builder.
 * Items must have `id`, `parent_id`, and `sort_order` properties.
 */
export function buildTree(items, parentId = null) {
    return items
        .filter((item) => item.parent_id === parentId)
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
        .map((item) => ({
            ...item,
            children: buildTree(items, item.id),
        }))
}
