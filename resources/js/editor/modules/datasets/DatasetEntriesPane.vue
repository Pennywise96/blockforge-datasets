<script setup>
import { computed, ref, watch } from 'vue'
import { BfButton, BfEmptyState, BfIcon, BfSearchInput, ModuleCollectionItem, ModuleScrollArea } from '@blockforge-cms/editor-sdk'
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
    selectedTypeName: {
        type: String,
        default: 'Dataset',
    },
    selectedCategoryName: {
        type: String,
        default: '',
    },
    visibilityLabel: {
        type: String,
        default: 'All',
    },
    totalEntries: {
        type: Number,
        default: 0,
    },
    selectedEntryId: {
        type: Number,
        default: null,
    },
})

const emit = defineEmits(['retry', 'select', 'delete', 'remove-category'])
const searchQuery = ref('')

const filteredEntries = computed(() => {
    const query = normalizeSearch(searchQuery.value)

    if (query === '') {
        return props.entries
    }

    return props.entries.filter((entry) => entryMatchesSearch(entry, query))
})

watch(
    () => props.hasSelectedType,
    (hasSelectedType) => {
        if (!hasSelectedType) {
            searchQuery.value = ''
        }
    },
)

watch(
    () => props.entries,
    (entries) => {
        if (entries.length === 0) {
            searchQuery.value = ''
        }
    },
)

function entryTitle(entry) {
    return entry.translations?.[props.locale]?.title ?? entry.slug
}

function normalizeSearch(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
}

function entryMatchesSearch(entry, query) {
    const haystack = [
        entryTitle(entry),
        entry.slug,
        entry.visibility_label,
        ...(entry.categories ?? []).map((category) => category.name),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

    return haystack.includes(query)
}

function visibilityPillClasses(entry) {
    if (entry?.is_visible_now) {
        return 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200'
    }

    if (entry?.visibility_mode === 'scheduled') {
        return 'border-amber-300/20 bg-amber-300/10 text-amber-200'
    }

    return 'border-white/[0.08] bg-white/[0.03] text-[var(--bf-ui-muted)]'
}

function categoryPillClasses(category) {
    return category.slug
        ? 'border-white/[0.08] bg-white/[0.04] text-white/82'
        : 'border-white/[0.08] bg-white/[0.03] text-[var(--bf-ui-muted)]'
}

function formatEntryDate(value) {
    if (!value) {
        return null
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return null
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() === new Date().getFullYear() ? undefined : 'numeric',
    }).format(date)
}

function entryUpdatedLabel(entry) {
    const formattedDate = formatEntryDate(entry.updated_at ?? entry.created_at)

    return formattedDate ? `Updated ${formattedDate}` : 'Recently updated'
}

function previewCategories(entry) {
    return Array.isArray(entry?.categories) ? entry.categories.slice(0, 3) : []
}

function hiddenCategoryCount(entry) {
    return Math.max((entry?.categories?.length ?? 0) - previewCategories(entry).length, 0)
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

            <div v-else class="px-3 py-3">
                <div class="overflow-hidden rounded-[16px] border border-[rgba(120,146,164,0.14)] bg-[#0d1216]">
                    <div class="border-b border-[rgba(120,146,164,0.12)] px-3 py-2.5">
                        <div class="flex items-center gap-2">
                            <BfSearchInput
                                v-model="searchQuery"
                                placeholder="Search this page by title, slug, or category..."
                                class="min-w-0 flex-1"
                            />

                            <span class="shrink-0 text-[11px] text-white/42">
                                {{ filteredEntries.length }} shown
                            </span>
                        </div>

                        <p class="mt-2 text-[11px] text-white/42">
                            {{ selectedCategoryName || 'All categories' }}
                            <span class="mx-1 text-white/24">/</span>
                            {{ visibilityLabel }}
                            <span class="mx-1 text-white/24">/</span>
                            {{ totalEntries || entries.length }} total
                        </p>
                    </div>

                    <div v-if="entries.length === 0" class="px-4 py-8">
                        <BfEmptyState
                            compact
                            icon="datasets"
                            title="No entries yet"
                            :description="selectedCategoryName
                                ? `No entries are assigned to “${selectedCategoryName}” yet.`
                                : `Create the first ${selectedTypeName.toLowerCase()} entry to start building this collection.`"
                        />
                    </div>

                    <div v-else-if="filteredEntries.length === 0" class="px-4 py-8">
                        <BfEmptyState
                            compact
                            icon="datasets"
                            title="No entries match this search"
                            :description="`No result for “${searchQuery.trim()}” on the current page.`"
                        >
                            <div class="pt-2">
                                <BfButton variant="secondary" size="sm" @click="searchQuery = ''">
                                    Clear search
                                </BfButton>
                            </div>
                        </BfEmptyState>
                    </div>

                    <div v-else>
                        <ModuleCollectionItem
                            v-for="entry in filteredEntries"
                            :key="entry.id"
                            layout="list"
                            :selected="selectedEntryId === entry.id"
                            draggable="true"
                            @dragstart="handleDragStart($event, entry)"
                            @dragend="handleDragEnd"
                            @click="emit('select', entry)"
                        >
                            <template #default>
                                <div class="min-w-0">
                                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                                        <p
                                            class="truncate text-[13px] font-medium tracking-[-0.01em]"
                                            :class="selectedEntryId === entry.id ? 'text-[#8edfff]' : 'text-white/92'"
                                        >
                                            {{ entryTitle(entry) }}
                                        </p>
                                        <span
                                            class="inline-flex min-h-[18px] items-center rounded-full border px-[8px] text-[10px] font-medium"
                                            :class="visibilityPillClasses(entry)"
                                        >
                                            {{ entry.visibility_label ?? (entry.is_visible_now ? 'Visible now' : 'Not visible') }}
                                        </span>
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-white/42">
                                        <span class="font-mono text-white/48">{{ entry.slug }}</span>
                                        <span class="text-white/24">/</span>
                                        <span>{{ entryUpdatedLabel(entry) }}</span>
                                        <span class="text-white/24">/</span>
                                        <span>{{ entry.categories?.length ?? 0 }} categories</span>
                                    </div>

                                    <div v-if="entry.categories?.length" class="mt-2 flex flex-wrap gap-1.5">
                                        <span
                                            v-for="category in previewCategories(entry)"
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

                                        <span
                                            v-if="hiddenCategoryCount(entry) > 0"
                                            class="inline-flex items-center rounded-full border border-white/[0.06] bg-white/[0.02] px-2 py-0.5 text-[10px] font-medium text-white/44"
                                        >
                                            +{{ hiddenCategoryCount(entry) }} more
                                        </span>
                                    </div>
                                </div>
                            </template>

                            <template #trailing="{ hovered }">
                                <BfButton
                                    variant="ghost"
                                    size="icon-sm"
                                    rounded="md"
                                    class="shrink-0 transition-[opacity,transform] duration-100"
                                    :class="hovered || selectedEntryId === entry.id ? 'translate-x-0 opacity-100' : 'translate-x-1 opacity-0'"
                                    title="Delete entry"
                                    @click.stop="emit('delete', entry)"
                                >
                                    <BfIcon name="delete" class="h-3 w-3 text-[var(--bf-ui-muted)] hover:text-red-400" />
                                </BfButton>
                            </template>
                        </ModuleCollectionItem>
                    </div>
                </div>
            </div>
        </ModuleScrollArea>
    </div>
</template>
