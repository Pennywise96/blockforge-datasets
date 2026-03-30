<script setup>
import { BfButton, BfEmptyState, BfIcon, ModuleCollectionItem, ModuleScrollArea } from '@blockforge-cms/editor-sdk'
import {
    clearDatasetEntryDragData,
    startDatasetEntryDragPreview,
    writeDatasetEntryDragData,
} from './entryCategoryDrag'

const props = defineProps({
    hasSelectedType: {
        type: Boolean,
        default: false,
    },
    entries: {
        type: Array,
        default: () => [],
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    locale: {
        type: String,
        default: 'en',
    },
    selectedEntryId: {
        type: Number,
        default: null,
    },
})

const emit = defineEmits(['retry', 'select', 'delete', 'remove-category'])

function entryTitle(entry) {
    return entry.translations?.[props.locale]?.title ?? entry.slug
}

function visibilityClasses(entry) {
    return entry?.is_visible_now
        ? 'text-emerald-500'
        : 'text-[var(--bf-ui-muted)]'
}

function categoryPillClasses(category) {
    return category.slug
        ? 'border-white/[0.08] bg-white/[0.04] text-white/82'
        : 'border-white/[0.08] bg-white/[0.03] text-[var(--bf-ui-muted)]'
}

function handleDragStart(event, entry) {
    writeDatasetEntryDragData(event.dataTransfer, {
        id: entry.id,
        slug: entry.slug,
        title: entryTitle(entry),
        categories: entry.categories ?? [],
    })
    startDatasetEntryDragPreview(event, {
        id: entry.id,
        slug: entry.slug,
        title: entryTitle(entry),
        categories: entry.categories ?? [],
    })
}

function handleDragEnd() {
    clearDatasetEntryDragData()
}
</script>

<template>
    <div class="absolute inset-0 flex flex-col">
        <ModuleScrollArea>
            <div v-if="!hasSelectedType" class="flex h-full items-center justify-center text-xs text-[var(--bf-ui-muted)]">
                Select a type to manage entries
            </div>

            <div v-else-if="isLoading" class="flex items-center justify-center py-8 text-xs text-[var(--bf-ui-muted)]">
                Loading…
            </div>

            <div v-else-if="error" class="px-4 py-6">
                <BfEmptyState
                    compact
                    icon="warning"
                    title="Could not load entries"
                    :description="error"
                >
                    <div class="pt-2">
                        <BfButton variant="secondary" size="sm" @click="emit('retry')">
                            Retry
                        </BfButton>
                    </div>
                </BfEmptyState>
            </div>

            <div v-else-if="entries.length === 0" class="flex items-center justify-center py-8 text-xs text-[var(--bf-ui-muted)]">
                No entries yet
            </div>

            <div v-else>
                <ModuleCollectionItem
                    v-for="entry in entries"
                    :key="entry.id"
                    layout="list"
                    :selected="selectedEntryId === entry.id"
                    draggable="true"
                    @dragstart="handleDragStart($event, entry)"
                    @dragend="handleDragEnd"
                    @click="emit('select', entry)"
                >
                    <template #default>
                        <p
                            class="truncate text-xs font-medium"
                            :class="selectedEntryId === entry.id ? 'text-[var(--bf-ui-accent)]' : 'text-[var(--bf-ui-text)]'"
                        >
                            {{ entryTitle(entry) }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-[var(--bf-ui-muted)]">
                            {{ entry.slug }}
                            <span
                                class="ml-1.5"
                                :class="visibilityClasses(entry)"
                            >
                                {{ entry.visibility_label ?? (entry.is_visible_now ? 'Visible now' : 'Not visible') }}
                            </span>
                        </p>

                        <div v-if="entry.categories?.length" class="mt-2 flex flex-wrap gap-1.5">
                            <span
                                v-for="category in entry.categories"
                                :key="category.id"
                                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium"
                                :class="categoryPillClasses(category)"
                            >
                                <span class="truncate">{{ category.name }}</span>
                                <button
                                    type="button"
                                    class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full text-white/54 transition-colors duration-100 hover:bg-white/[0.08] hover:text-white/92"
                                    title="Remove category"
                                    @click.stop="emit('remove-category', entry, category)"
                                >
                                    <BfIcon name="close" class="h-2.5 w-2.5" />
                                </button>
                            </span>
                        </div>
                    </template>

                    <template #trailing="{ hovered }">
                        <BfButton
                            variant="ghost"
                            size="icon-sm"
                            rounded="md"
                            class="shrink-0"
                            :class="hovered ? 'opacity-100' : 'opacity-0'"
                            title="Delete entry"
                            @click.stop="emit('delete', entry)"
                        >
                            <BfIcon name="delete" class="h-3 w-3 text-[var(--bf-ui-muted)] hover:text-red-400" />
                        </BfButton>
                    </template>
                </ModuleCollectionItem>
            </div>
        </ModuleScrollArea>
    </div>
</template>
