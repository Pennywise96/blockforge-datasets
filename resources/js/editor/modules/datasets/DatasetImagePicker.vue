<script setup>
import { computed, ref, watch } from 'vue'
import { BfButton, BfField, BfSelect, fetchMediaCategories, fetchMediaItems, mediaFileUrl, ModuleCollectionItem } from '@blockforge-cms/editor-sdk'

const props = defineProps({
    modelValue: {
        type: Object,
        default: null,
    },
    locale: {
        type: String,
        default: 'en',
    },
})

const emit = defineEmits(['update:modelValue'])

const pickerOpen = ref(false)
const mediaItems = ref([])
const mediaCategories = ref([])
const selectedCategoryId = ref(null)
const loadingMedia = ref(false)
const loadingCategories = ref(false)
const error = ref(null)

const selectedImage = computed(() => props.modelValue ?? null)
const selectedImageUrl = computed(() => mediaFileUrl(selectedImage.value))
const categoryOptions = computed(() => [
    { id: null, name: 'All categories', depth: 0 },
    ...flattenCategories(mediaCategories.value),
])
const pickerButtonLabel = computed(() => selectedImage.value ? 'Replace image' : 'Pick image')

function flattenCategories(categories, parentId = null, depth = 0) {
    return categories
        .filter((category) => category.parent_id === parentId)
        .flatMap((category) => [
            {
                id: category.id,
                name: category.name,
                depth,
            },
            ...flattenCategories(categories, category.id, depth + 1),
        ])
}

function mediaItemLabel(item) {
    const translation = item?.translations?.[props.locale] ?? {}

    return translation.title ?? translation.alt ?? item?.filename ?? 'Untitled image'
}

function mediaItemMeta(item) {
    if (!item) {
        return 'No image selected'
    }

    if (item.width && item.height) {
        return `${item.width} x ${item.height}`
    }

    return item.mime_type ?? 'Image'
}

async function loadMedia() {
    loadingMedia.value = true
    error.value = null

    try {
        const data = await fetchMediaItems({
            categoryId: selectedCategoryId.value,
            perPage: 80,
        })

        mediaItems.value = data.data ?? data
    } catch {
        error.value = 'Failed to load the media library.'
    } finally {
        loadingMedia.value = false
    }
}

async function loadCategories() {
    loadingCategories.value = true
    error.value = null

    try {
        mediaCategories.value = await fetchMediaCategories()
    } catch {
        error.value = 'Failed to load media categories.'
    } finally {
        loadingCategories.value = false
    }
}

async function togglePicker() {
    pickerOpen.value = !pickerOpen.value

    if (!pickerOpen.value) {
        return
    }

    if (mediaCategories.value.length === 0) {
        await loadCategories()
    }

    if (mediaItems.value.length === 0) {
        await loadMedia()
    }
}

function pickImage(item) {
    emit('update:modelValue', item ? { ...item } : null)
    pickerOpen.value = false
}

function clearImage() {
    emit('update:modelValue', null)
}

watch(selectedCategoryId, async () => {
    if (!pickerOpen.value) {
        return
    }

    await loadMedia()
})
</script>

<template>
    <div class="space-y-3">
        <div class="overflow-hidden rounded-2xl border border-[var(--bf-ui-border)] bg-[var(--bf-ui-panel-soft)]">
            <div v-if="selectedImage" class="grid gap-3 p-3 md:grid-cols-[minmax(0,180px)_1fr]">
                <div class="overflow-hidden rounded-xl border border-[var(--bf-ui-border)] bg-[var(--bf-ui-panel-strong)]">
                    <img
                        v-if="selectedImageUrl"
                        :src="selectedImageUrl"
                        :alt="mediaItemLabel(selectedImage)"
                        class="aspect-[4/3] h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex aspect-[4/3] items-center justify-center px-3 text-center text-xs text-[var(--bf-ui-muted)]"
                    >
                        Preview unavailable
                    </div>
                </div>

                <div class="flex min-w-0 flex-col justify-between gap-3">
                    <div class="space-y-1">
                        <p class="truncate text-sm font-medium text-[var(--bf-ui-text)]">
                            {{ mediaItemLabel(selectedImage) }}
                        </p>
                        <p class="text-xs text-[var(--bf-ui-muted)]">
                            {{ mediaItemMeta(selectedImage) }}
                        </p>
                        <p class="truncate text-[11px] text-[var(--bf-ui-muted)]">
                            {{ selectedImage.filename }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <BfButton
                            variant="secondary"
                            size="sm"
                            rounded="lg"
                            @click="togglePicker"
                        >
                            {{ pickerButtonLabel }}
                        </BfButton>
                        <BfButton
                            variant="ghost"
                            size="sm"
                            rounded="lg"
                            @click="clearImage"
                        >
                            Remove
                        </BfButton>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col gap-3 p-4">
                <div class="rounded-xl border border-dashed border-[var(--bf-ui-border)] bg-[var(--bf-ui-panel)] px-4 py-6 text-center">
                    <p class="text-sm font-medium text-[var(--bf-ui-text)]">No image selected</p>
                    <p class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-muted)]">
                        Pick an existing asset from the media library for this dataset entry.
                    </p>
                </div>

                <BfButton
                    variant="secondary"
                    size="sm"
                    rounded="lg"
                    class="self-start"
                    @click="togglePicker"
                >
                    Pick image
                </BfButton>
            </div>
        </div>

        <div
            v-if="pickerOpen"
            class="overflow-hidden rounded-2xl border border-[var(--bf-ui-border)] bg-[var(--bf-ui-panel-soft)]"
        >
            <div class="flex flex-col gap-3 border-b border-[var(--bf-ui-border)] px-3 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--bf-ui-muted)]">
                            Media library
                        </p>
                        <p class="mt-1 text-xs text-[var(--bf-ui-text)]">
                            Select an image to store with the current translation.
                        </p>
                    </div>

                    <BfButton
                        variant="ghost"
                        size="sm"
                        rounded="lg"
                        @click="togglePicker"
                    >
                        Close
                    </BfButton>
                </div>

                <BfField label="Category">
                    <BfSelect
                        v-model="selectedCategoryId"
                        :disabled="loadingCategories"
                    >
                        <option
                            v-for="option in categoryOptions"
                            :key="option.id ?? 'all-categories'"
                            :value="option.id"
                        >
                            {{ `${'— '.repeat(option.depth)}${option.name}` }}
                        </option>
                    </BfSelect>
                </BfField>
            </div>

            <div v-if="error" class="px-3 py-4 text-xs text-red-500">
                {{ error }}
            </div>
            <div v-else-if="loadingMedia" class="px-3 py-6 text-center text-xs text-[var(--bf-ui-muted)]">
                Loading images…
            </div>
            <div v-else-if="mediaItems.length === 0" class="px-3 py-6 text-center text-xs text-[var(--bf-ui-muted)]">
                No images found for the selected category.
            </div>
            <div
                v-else
                class="grid gap-2.5 p-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <ModuleCollectionItem
                    v-for="item in mediaItems"
                    :key="item.id"
                    layout="grid"
                    :selected="selectedImage?.id === item.id"
                    @click="pickImage(item)"
                >
                    <template #media>
                        <img
                            :src="mediaFileUrl(item)"
                            :alt="mediaItemLabel(item)"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                    </template>

                    <template #default>
                        <p class="truncate text-xs leading-none text-[var(--bf-ui-text)]">{{ mediaItemLabel(item) }}</p>
                        <p class="mt-0.5 text-xs text-[var(--bf-ui-muted)]">{{ mediaItemMeta(item) }}</p>
                    </template>
                </ModuleCollectionItem>
            </div>
        </div>
    </div>
</template>
