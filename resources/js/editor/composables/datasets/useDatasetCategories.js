import { computed } from 'vue'
import { useCreateChildTreeAction, useDeleteTreeAction, useDragAndDrop, useSettingsTreeAction } from '@blockforge-cms/editor-sdk'
import { hasDatasetEntryDragData, readDatasetEntryDragData } from '../../modules/datasets/entryCategoryDrag'

export function useDatasetCategories(store, options = {}) {
    const tree = computed(() => store.categoryTree)
    const entryDropTargetId = computed(() => options.entryDropTargetId?.value ?? null)

    async function deleteCategory(category) {
        if (!confirm(`Delete "${category.name}"?`)) {
            return
        }

        await store.deleteCategoryById(category.id, category.slug)
    }

    function isSourceItem(item) {
        return dnd.state.sourceId === item.id
    }

    function dropPosition(item) {
        return dnd.state.targetId === item.id ? dnd.state.position : null
    }

    function isDescendantCategory(sourceId, candidateId) {
        let current = store.categories.find((category) => category.id === candidateId) ?? null

        while (current?.parent_id) {
            if (current.parent_id === sourceId) {
                return true
            }

            current = store.categories.find((category) => category.id === current.parent_id) ?? null
        }

        return false
    }

    const createChildAction = useCreateChildTreeAction({
        title: 'Add sub-category',
        onClick: (item) => store.openCreateCategory(item),
    })

    const settingsAction = useSettingsTreeAction({
        title: 'Category settings',
        onClick: (item) => options.onOpenSettings?.(item),
    })

    const deleteAction = useDeleteTreeAction({
        title: 'Delete category',
        onClick: (item) => deleteCategory(item),
    })

    const dnd = useDragAndDrop({
        onDrop({ sourceId, targetId, position }) {
            const source = store.categories.find((category) => category.id === sourceId)
            const target = store.categories.find((category) => category.id === targetId)

            if (!source || !target || isDescendantCategory(source.id, target.id)) {
                return
            }

            if (position === 'into') {
                const childCount = store.categories.filter((category) => category.parent_id === target.id).length
                store.moveCategory(source.id, target.id, childCount)

                return
            }

            const parentId = target.parent_id
            const siblings = store.categories
                .filter((category) => category.parent_id === parentId && category.id !== source.id)
                .sort((left, right) => (left.sort_order ?? 0) - (right.sort_order ?? 0))
            const targetIndex = siblings.findIndex((category) => category.id === target.id)

            store.moveCategory(source.id, parentId, position === 'before' ? targetIndex : targetIndex + 1)
        },
    })

    const treeDefinition = {
        getKey(item) {
            return item.id
        },
        getChildren(item) {
            return item?.children ?? []
        },
        getNodeState(item) {
            const rowBindings = composeBindings(
                dnd.dropProps(item.id, { nestable: true }),
                dnd.handleProps(item.id),
                resolveEntryDropBindings(item),
                { title: 'Drag to reorder' },
            )

            return {
                initiallyExpanded: true,
                selected: store.selectedCategorySlug === item.slug,
                dimmed: isSourceItem(item),
                interactive: true,
                cursor: 'grab',
                rowBindings,
                dropPosition: dropPosition(item),
                entryDropActive: entryDropTargetId.value === item.id,
            }
        },
        getActions(item) {
            return [
                settingsAction(item),
                createChildAction(item),
                deleteAction(item),
            ]
        },
        onRowClick(item) {
            store.selectCategory(item.slug)
        },
    }

    return {
        tree,
        treeDefinition,
    }

    function resolveEntryDropBindings(item) {
        function readPayload(event) {
            const payload = readDatasetEntryDragData(event?.dataTransfer)

            if (!payload) {
                return null
            }

            if (payload.categoryIds.includes(item.id)) {
                return null
            }

            return payload
        }

        return {
            onDragenter(event) {
                if (!hasDatasetEntryDragData(event?.dataTransfer)) {
                    return
                }

                if (!readPayload(event)) {
                    options.onEntryDragLeave?.(item)
                    return
                }

                event.preventDefault()
                options.onEntryDragEnter?.(item)
            },
            onDragover(event) {
                if (!hasDatasetEntryDragData(event?.dataTransfer)) {
                    return
                }

                if (!readPayload(event)) {
                    event.dataTransfer.dropEffect = 'none'
                    options.onEntryDragLeave?.(item)
                    return
                }

                event.preventDefault()
                event.dataTransfer.dropEffect = 'copy'
                options.onEntryDragEnter?.(item)
            },
            onDragleave() {
                options.onEntryDragLeave?.(item)
            },
            async onDrop(event) {
                const payload = readPayload(event)

                if (!payload) {
                    options.onEntryDragLeave?.(item)
                    return
                }

                event.preventDefault()
                event.stopPropagation()
                options.onEntryDragLeave?.(item)
                await store.assignEntryCategory(payload.entryId, item.id)
            },
        }
    }

    function composeBindings(...bindingSets) {
        const merged = {}

        for (const bindings of bindingSets) {
            for (const [key, value] of Object.entries(bindings ?? {})) {
                if (!key.startsWith('on') || typeof value !== 'function') {
                    merged[key] = value
                    continue
                }

                const existing = merged[key]

                if (typeof existing === 'function') {
                    merged[key] = async (...args) => {
                        await existing(...args)
                        await value(...args)
                    }

                    continue
                }

                merged[key] = value
            }
        }

        return merged
    }
}
