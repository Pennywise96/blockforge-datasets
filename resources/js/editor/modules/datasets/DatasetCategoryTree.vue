<script setup>
import { computed, ref } from 'vue'
import { BfButton, BfIcon, ModuleBody, ModuleHeader, ModuleScrollArea, ModuleStackLayout, TreeItemRow, TreeView } from '@blockforge-cms/editor-sdk'
import { useDatasetCategories } from '../../composables/datasets/useDatasetCategories'
import { useDatasetsStore } from '../../stores/datasets'
import DatasetCategoryFormPane from './DatasetCategoryFormPane.vue'

const datasetsStore = useDatasetsStore()
const entryDropTargetId = ref(null)

const { tree: categoryTree, treeDefinition } = useDatasetCategories(datasetsStore, {
    onOpenSettings(category) {
        datasetsStore.cancelCreateCategory()
        datasetsStore.openCategorySettings(category.id)
    },
    entryDropTargetId,
    onEntryDragEnter(category) {
        entryDropTargetId.value = category.id
    },
    onEntryDragLeave(category) {
        if (entryDropTargetId.value === category.id) {
            entryDropTargetId.value = null
        }
    },
})

const isEditingCategory = computed(() => Boolean(datasetsStore.editingCategory))
const isCreatingCategory = computed(() => datasetsStore.isCreatingCategory)
const isCategoryPanelOpen = computed(() => isEditingCategory.value || isCreatingCategory.value)
const createParentCategory = computed(() =>
    datasetsStore.categories.find((category) => category.id === datasetsStore.createCategoryParentId) ?? null,
)
const headerTitle = computed(() => {
    if (isCreatingCategory.value) {
        return datasetsStore.createCategoryParentId ? 'New sub-category' : 'New category'
    }

    return isEditingCategory.value ? 'Category settings' : 'Categories'
})

function closeCategoryPanel() {
    if (isCreatingCategory.value) {
        datasetsStore.cancelCreateCategory()
        return
    }

    datasetsStore.closeCategorySettings()
}
</script>

<template>
    <ModuleStackLayout>
        <ModuleHeader class="px-3 py-2">
            <div class="flex items-center gap-1">
                <Transition name="header-back">
                    <BfButton
                        v-if="isCategoryPanelOpen"
                        variant="ghost"
                        size="icon-sm"
                        rounded="md"
                        class="shrink-0"
                        title="Back to categories"
                        @click="closeCategoryPanel"
                    >
                        <BfIcon name="back" class="h-3.5 w-3.5" />
                    </BfButton>
                </Transition>

                <span class="flex-1 truncate text-xs font-medium text-[var(--bf-ui-muted)]">
                    {{ headerTitle }}
                </span>
                <BfButton
                    v-if="!isCategoryPanelOpen"
                    variant="ghost"
                    size="icon-sm"
                    rounded="md"
                    title="Add category"
                    @click="datasetsStore.openCreateCategory()"
                >
                    <BfIcon name="plus" class="w-3.5 h-3.5" />
                </BfButton>
            </div>
        </ModuleHeader>

        <ModuleBody class="relative overflow-hidden">
            <Transition name="drill-back">
                <div v-if="!isCategoryPanelOpen" class="absolute inset-0 flex flex-col">
                    <ModuleScrollArea class="py-1">
                        <TreeItemRow
                            :selected="datasetsStore.selectedCategorySlug === null"
                            interactive
                            @click="datasetsStore.selectCategory(null)"
                        >
                            <template #default>
                                <BfIcon name="squares-2x2" class="h-3.5 w-3.5 shrink-0 text-[var(--bf-ui-muted)]" />
                                <span
                                    class="text-xs"
                                    :class="datasetsStore.selectedCategorySlug === null ? 'text-[var(--bf-ui-accent)]' : 'text-[var(--bf-ui-muted)]'"
                                >
                                    All entries
                                </span>
                            </template>
                        </TreeItemRow>

                        <TreeView :items="categoryTree" :definition="treeDefinition">
                            <template #leading="{ item }">
                                <BfIcon
                                    name="folder"
                                    class="h-3.5 w-3.5 shrink-0 transition-colors duration-75"
                                    :class="datasetsStore.selectedCategorySlug === item.slug ? 'text-[var(--bf-ui-accent)]' : 'text-[var(--bf-ui-muted)]'"
                                />
                            </template>

                            <template #label="{ item }">
                                <span
                                    class="flex-1 truncate text-xs leading-none"
                                    :class="entryDropTargetId === item.id || datasetsStore.selectedCategorySlug === item.slug ? 'text-[var(--bf-ui-accent)]' : 'text-[var(--bf-ui-text)]'"
                                >
                                    {{ item.name }}
                                </span>
                            </template>

                            <template #meta="{ item }">
                                <span
                                    v-if="entryDropTargetId === item.id"
                                    class="text-[10px] font-medium uppercase tracking-[0.08em] text-[var(--bf-ui-accent)]"
                                >
                                    Assign
                                </span>
                            </template>
                        </TreeView>

                        <div v-if="categoryTree.length === 0" class="px-2 py-1 text-xs text-[var(--bf-ui-muted)]">
                            No categories yet
                        </div>
                    </ModuleScrollArea>
                </div>
            </Transition>

            <Transition name="drill-forward">
                <DatasetCategoryFormPane
                    v-if="isCategoryPanelOpen"
                    :is-creating="isCreatingCategory"
                    :create-parent-category-name="createParentCategory?.name ?? null"
                    :editing-category-name="datasetsStore.editingCategory?.name ?? null"
                    :form="datasetsStore.categoryDetailForm"
                    :is-saving-create="datasetsStore.isSavingCategory"
                    :is-saving-detail="datasetsStore.isSavingCategoryDetail"
                    @save="isCreatingCategory ? datasetsStore.submitCreateCategory() : datasetsStore.saveCategoryDetail()"
                    @delete="datasetsStore.deleteCategoryById(datasetsStore.editingCategory.id, datasetsStore.editingCategory.slug)"
                    @sync-slug="datasetsStore.syncCategorySlug"
                />
            </Transition>
        </ModuleBody>
    </ModuleStackLayout>
</template>

<style scoped>
@layer cms-editor {
.drill-forward-enter-active,
.drill-forward-leave-active,
.drill-back-enter-active,
.drill-back-leave-active {
    transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.drill-forward-enter-from {
    opacity: 0;
    transform: translateX(24px);
}

.drill-forward-leave-to {
    opacity: 0;
    transform: translateX(-24px);
}

.drill-back-enter-from {
    opacity: 0;
    transform: translateX(-24px);
}

.drill-back-leave-to {
    opacity: 0;
    transform: translateX(24px);
}

.header-back-enter-active,
.header-back-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.header-back-enter-from,
.header-back-leave-to {
    opacity: 0;
    transform: translateX(-6px);
}
}
</style>
