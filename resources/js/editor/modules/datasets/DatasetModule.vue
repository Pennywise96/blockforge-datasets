<script setup>
import { computed, onMounted } from 'vue'
import { BfButton, BfIcon, BfSelect, ModuleBody, ModuleFooterBar, ModuleHeader, ModuleMain, ModuleSidebar, ModuleSplitLayout } from '@blockforge-cms/editor-sdk'
import { useDatasetsStore } from '../../stores/datasets'
import DatasetCategoryTree from './DatasetCategoryTree.vue'
import DatasetEntriesPane from './DatasetEntriesPane.vue'
import DatasetEntryCreatePane from './DatasetEntryCreatePane.vue'
import DatasetEntryDetailPane from './DatasetEntryDetailPane.vue'
import DatasetTypeList from './DatasetTypeList.vue'

const datasetsStore = useDatasetsStore()

const selectedTypeLabel = computed(() =>
    datasetsStore.selectedType ? datasetsStore.selectedType.name : 'Datasets',
)
const isEditingEntry = computed(() => Boolean(datasetsStore.selectedEntry))
const isCreatingEntry = computed(() => datasetsStore.isCreatingEntry)
const isEntryPanelOpen = computed(() => isEditingEntry.value || isCreatingEntry.value)
const headerTitle = computed(() => {
    if (isCreatingEntry.value) {
        return 'New entry'
    }

    if (isEditingEntry.value) {
        return datasetsStore.selectedEntry?.translations?.[datasetsStore.locale]?.title
            ?? datasetsStore.selectedEntry?.slug
            ?? 'Edit entry'
    }

    return selectedTypeLabel.value
})

async function deleteEntry(entry) {
    if (!confirm(`Delete "${entry.translations?.[datasetsStore.locale]?.title ?? entry.slug}"?`)) {
        return
    }

    await datasetsStore.deleteEntryById(entry.id)
}

function openEntry(entry) {
    datasetsStore.selectEntry(entry.id)
}

function closeEntryPanel() {
    if (isCreatingEntry.value) {
        datasetsStore.cancelCreateEntry()
        return
    }

    datasetsStore.closeSelectedEntry()
}

onMounted(async () => {
    await datasetsStore.initialize()
})
</script>

<template>
    <ModuleSplitLayout>
        <ModuleSidebar style="width: 200px;">
            <ModuleBody>
                <div class="flex h-full min-h-0 flex-col">
                    <div class="shrink-0 dataset-types-rail">
                        <DatasetTypeList />
                    </div>
                    <div v-if="datasetsStore.selectedType" class="min-h-0 flex-1 border-t border-[var(--bf-ui-border)]">
                        <DatasetCategoryTree />
                    </div>
                    <div v-else class="flex items-center justify-center border-t border-[var(--bf-ui-border)] px-3 py-4 text-center text-xs text-[var(--bf-ui-muted)]">
                        Select a type to see its categories.
                    </div>
                </div>
            </ModuleBody>
        </ModuleSidebar>

        <ModuleMain>
            <ModuleHeader class="px-3 py-2">
                <div class="flex items-center gap-2">
                    <Transition name="header-back">
                        <BfButton
                            v-if="isEntryPanelOpen"
                            variant="ghost"
                            size="icon-sm"
                            rounded="md"
                            class="shrink-0"
                            title="Back to entries"
                            @click="closeEntryPanel"
                        >
                            <BfIcon name="back" class="h-3.5 w-3.5" />
                        </BfButton>
                    </Transition>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h2 class="truncate text-sm font-semibold text-[var(--bf-ui-text)]">
                                {{ headerTitle }}
                            </h2>
                            <span v-if="!isEntryPanelOpen && datasetsStore.pagination" class="bf-badge bf-badge--neutral">
                                {{ datasetsStore.pagination.total }}
                            </span>
                        </div>
                    </div>

                    <BfSelect
                        v-if="datasetsStore.selectedType && !isEntryPanelOpen"
                        v-model="datasetsStore.statusFilter"
                        class="w-auto"
                        @change="datasetsStore.setStatusFilter(datasetsStore.statusFilter)"
                    >
                        <option value="all">All</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </BfSelect>

                    <BfButton
                        v-if="datasetsStore.selectedType && !isEntryPanelOpen"
                        variant="primary"
                        size="sm"
                        rounded="lg"
                        @click="datasetsStore.openCreateEntry()"
                    >
                        + New
                    </BfButton>
                </div>
            </ModuleHeader>

            <ModuleBody class="relative overflow-hidden">
                <Transition name="drill-back">
                    <DatasetEntriesPane
                        v-if="!isEntryPanelOpen"
                        :has-selected-type="Boolean(datasetsStore.selectedType)"
                        :entries="datasetsStore.entries"
                        :is-loading="datasetsStore.isLoadingEntries"
                        :error="datasetsStore.entriesError"
                        :locale="datasetsStore.locale"
                        :selected-entry-id="datasetsStore.selectedEntryId"
                        :format-date="datasetsStore.formatDate"
                        @retry="datasetsStore.loadEntries()"
                        @select="openEntry"
                        @delete="deleteEntry"
                        @remove-category="(entry, category) => datasetsStore.removeEntryCategory(entry.id, category.id)"
                    />
                </Transition>

                <Transition name="drill-forward">
                    <DatasetEntryCreatePane
                        v-if="isCreatingEntry && datasetsStore.selectedType"
                        :form="datasetsStore.createEntryForm"
                        :is-saving="datasetsStore.isSavingEntry"
                        @save="datasetsStore.submitCreateEntry"
                        @sync-slug="datasetsStore.syncCreateEntrySlug"
                    />
                </Transition>

                <Transition name="drill-forward">
                    <DatasetEntryDetailPane
                        v-if="isEditingEntry"
                        :entry="datasetsStore.selectedEntry"
                        :form="datasetsStore.detailForm"
                        :is-saving="datasetsStore.isSavingDetail"
                        @save="datasetsStore.saveEntryDetail"
                    />
                </Transition>
            </ModuleBody>

            <ModuleFooterBar
                v-if="!isEntryPanelOpen && datasetsStore.pagination && datasetsStore.pagination.last_page > 1"
                class="flex items-center justify-between"
            >
                <BfButton
                    variant="ghost"
                    size="sm"
                    :disabled="datasetsStore.currentPage <= 1"
                    @click="datasetsStore.loadEntries(datasetsStore.currentPage - 1)"
                >
                    ← Prev
                </BfButton>
                <span class="text-xs text-[var(--bf-ui-muted)]">{{ datasetsStore.currentPage }} / {{ datasetsStore.pagination.last_page }}</span>
                <BfButton
                    variant="ghost"
                    size="sm"
                    :disabled="datasetsStore.currentPage >= datasetsStore.pagination.last_page"
                    @click="datasetsStore.loadEntries(datasetsStore.currentPage + 1)"
                >
                    Next →
                </BfButton>
            </ModuleFooterBar>
        </ModuleMain>
    </ModuleSplitLayout>
</template>

<style scoped>
@layer cms-editor {
.dataset-types-rail {
    height: 176px;
}

.drill-forward-enter-active,
.drill-forward-leave-active,
.drill-back-enter-active,
.drill-back-leave-active {
    transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.drill-forward-enter-from {
    opacity: 0;
    transform: translateX(32px);
}

.drill-forward-leave-to {
    opacity: 0;
    transform: translateX(-32px);
}

.drill-back-enter-from {
    opacity: 0;
    transform: translateX(-32px);
}

.drill-back-leave-to {
    opacity: 0;
    transform: translateX(32px);
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
