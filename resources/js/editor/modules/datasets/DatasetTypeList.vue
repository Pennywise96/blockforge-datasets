<script setup>
import { BfButton, BfEmptyState, BfIcon, ModuleBody, ModuleHeader, ModuleScrollArea, ModuleStackLayout } from '@blockforge-cms/editor-sdk'
import { useDatasetsStore } from '../../stores/datasets'
import DatasetTypeFormPane from './DatasetTypeFormPane.vue'

const datasetsStore = useDatasetsStore()

async function deleteType(type) {
    if (!confirm(`Delete type "${type.name}" and all its entries?`)) {
        return
    }

    await datasetsStore.deleteTypeById(type.id)
}
</script>

<template>
    <ModuleStackLayout>
        <ModuleHeader class="px-3 py-2">
            <div class="flex items-center gap-1">
                <Transition name="header-back">
                    <BfButton
                        v-if="datasetsStore.isCreatingType"
                        variant="ghost"
                        size="icon-sm"
                        rounded="md"
                        class="shrink-0"
                        title="Back to types"
                        @click="datasetsStore.cancelCreateType()"
                    >
                        <BfIcon name="back" class="h-3.5 w-3.5" />
                    </BfButton>
                </Transition>

                <span class="flex-1 truncate text-xs font-medium text-[var(--bf-ui-muted)]">
                    {{ datasetsStore.isCreatingType ? 'New type' : 'Types' }}
                </span>

                <BfButton
                    v-if="!datasetsStore.isCreatingType"
                    variant="ghost"
                    size="icon-sm"
                    rounded="md"
                    title="Add type"
                    @click="datasetsStore.openCreateType()"
                >
                    <BfIcon name="plus" class="w-3.5 h-3.5" />
                </BfButton>
            </div>
        </ModuleHeader>

        <ModuleBody class="relative overflow-hidden">
            <Transition name="drill-back">
                <div v-if="!datasetsStore.isCreatingType" class="absolute inset-0 flex flex-col">
                    <ModuleScrollArea class="py-1">
                        <div
                            v-if="datasetsStore.isLoadingTypes"
                            class="px-3 py-3 text-xs text-[var(--bf-ui-muted)]"
                        >
                            Loading types…
                        </div>

                        <div
                            v-else-if="datasetsStore.typesError"
                            class="px-3 py-3"
                        >
                            <BfEmptyState
                                compact
                                icon="warning"
                                title="Could not load dataset types"
                                :description="datasetsStore.typesError"
                            >
                                <div class="pt-2">
                                    <BfButton variant="secondary" size="sm" @click="datasetsStore.loadTypes()">
                                        Retry
                                    </BfButton>
                                </div>
                            </BfEmptyState>
                        </div>

                        <template v-else>
                            <div
                                v-for="type in datasetsStore.types"
                                :key="type.id"
                                class="group flex items-center gap-2 h-7 px-2 rounded cursor-pointer transition-colors duration-75"
                                :class="datasetsStore.selectedTypeId === type.id ? 'bg-[var(--bf-ui-accent-12)]' : 'hover:bg-black/5'"
                                @click="datasetsStore.selectType(type)"
                            >
                                <span
                                    class="flex-1 truncate text-xs"
                                    :class="datasetsStore.selectedTypeId === type.id ? 'text-[var(--bf-ui-accent)]' : 'text-[var(--bf-ui-text)]'"
                                >
                                    {{ type.name }}
                                </span>
                                <span
                                    class="shrink-0 rounded-full border px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-[0.08em]"
                                    :class="type.schema_status === 'available'
                                        ? 'border-emerald-500/30 text-emerald-500'
                                        : 'border-[var(--bf-ui-border)] text-[var(--bf-ui-muted)]'"
                                >
                                    {{ type.schema_status === 'available' ? 'schema' : 'built-in' }}
                                </span>
                                <BfButton
                                    variant="ghost"
                                    size="icon-sm"
                                    class="opacity-0 group-hover:opacity-100"
                                    title="Delete type"
                                    @click.stop="deleteType(type)"
                                >
                                    <BfIcon name="delete" class="w-3 h-3 hover:text-red-400" />
                                </BfButton>
                            </div>
                        </template>

                        <div v-if="!datasetsStore.isLoadingTypes && !datasetsStore.typesError && datasetsStore.types.length === 0" class="px-3 py-3">
                            <BfEmptyState
                                compact
                                icon="datasets"
                                title="No types yet"
                                description="Create the first dataset type to start managing entries."
                            >
                                <div class="pt-2">
                                    <BfButton variant="primary" size="sm" @click="datasetsStore.openCreateType()">
                                        Create first type
                                    </BfButton>
                                </div>
                            </BfEmptyState>
                        </div>
                    </ModuleScrollArea>
                </div>
            </Transition>

            <Transition name="drill-forward">
                <DatasetTypeFormPane
                    v-if="datasetsStore.isCreatingType"
                    :form="datasetsStore.createTypeForm"
                    :is-saving="datasetsStore.isSavingType"
                    @sync-code="datasetsStore.syncCreateTypeCode"
                    @save="datasetsStore.submitCreateType"
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
