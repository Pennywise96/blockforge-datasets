<script setup>
import { computed, ref, watch } from 'vue'
import { BfButton, BfField, BfSelect, fetchMediaCategories, fetchMediaItems, mediaFileUrl, ModuleCollectionItem } from '@blockforge-cms/editor-sdk'

const props = defineProps({
    modelValue: {
        type: [Object, Array],
        default: null,
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
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

const selectedImages = computed(() => {
    if (props.multiple) {
        return Array.isArray(props.modelValue) ? props.modelValue : []
    }

    return props.modelValue ? [props.modelValue] : []
})
const selectedImage = computed(() => selectedImages.value[0] ?? null)
const selectedImageUrl = computed(() => mediaFileUrl(selectedImage.value))
const categoryOptions = computed(() => [
    { id: null, name: 'All categories', depth: 0 },
    ...flattenCategories(mediaCategories.value),
])
const pickerButtonLabel = computed(() => {
    if (props.multiple) {
        return selectedImages.value.length > 0 ? 'Manage images' : 'Pick images'
    }

    return selectedImage.value ? 'Replace image' : 'Pick image'
})

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
    if (props.disabled) {
        return
    }

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
    if (props.multiple) {
        const current = [...selectedImages.value]
        const exists = current.some((candidate) => candidate?.id === item?.id)

        emit('update:modelValue', exists
            ? current.filter((candidate) => candidate?.id !== item?.id)
            : [...current, { ...item }])

        return
    }

    emit('update:modelValue', item ? { ...item } : null)
    pickerOpen.value = false
}

function clearImage(index = null) {
    if (props.multiple) {
        if (index === null) {
            emit('update:modelValue', [])
            return
        }

        const nextImages = [...selectedImages.value]
        nextImages.splice(index, 1)
        emit('update:modelValue', nextImages)
        return
    }

    emit('update:modelValue', null)
}

function isSelected(item) {
    return selectedImages.value.some((candidate) => candidate?.id === item?.id)
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
            <div v-if="!multiple && selectedImage" class="grid gap-3 p-3 md:grid-cols-[minmax(0,180px)_1fr]">
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
                            :disabled="disabled"
                            @click="togglePicker"
                        >
                            {{ pickerButtonLabel }}
                        </BfButton>
                        <BfButton
                            variant="ghost"
                            size="sm"
                            rounded="lg"
                            :disabled="disabled"
                            @click="clearImage"
                        >
                            Remove
                        </BfButton>
                    </div>
                </div>
            </div>

            <div v-else-if="multiple && selectedImages.length" class="flex flex-col gap-3 p-4">
                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="(item, index) in selectedImages"
                        :key="item.id ?? index"
                        class="group relative h-20 w-20 overflow-hidden rounded-xl border border-[var(--bf-ui-border)] bg-[var(--bf-ui-panel-strong)]"
                    >
                        <img
                            v-if="mediaFileUrl(item)"
                            :src="mediaFileUrl(item)"
                            :alt="mediaItemLabel(item)"
                            class="h-full w-full object-cover"
                        />

                        <button
                            type="button"
                            class="absolute right-1 top-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-black/60 text-white/80 transition-colors duration-100 hover:bg-black/80"
                            :disabled="disabled"
                            @click="clearImage(index)"
                        >
                            ×
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <BfButton
                        variant="secondary"
                        size="sm"
                        rounded="lg"
                        :disabled="disabled"
                        @click="togglePicker"
                    >
                        {{ pickerButtonLabel }}
                    </BfButton>
                    <BfButton
                        variant="ghost"
                        size="sm"
                        rounded="lg"
                        :disabled="disabled"
                        @click="clearImage()"
                    >
                        Remove all
                    </BfButton>
                </div>
            </div>

            <div v-else class="flex flex-col gap-3 p-4">
                <div class="rounded-xl border border-dashed border-[var(--bf-ui-border)] bg-[var(--bf-ui-panel)] px-4 py-6 text-center">
                    <p class="text-sm font-medium text-[var(--bf-ui-text)]">
                        {{ multiple ? 'No images selected' : 'No image selected' }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-muted)]">
                        Pick existing assets from the media library for this dataset entry.
                    </p>
                </div>

                <BfButton
                    variant="secondary"
                    size="sm"
                    rounded="lg"
                    class="self-start"
                    :disabled="disabled"
                    @click="togglePicker"
                >
                    {{ multiple ? 'Pick images' : 'Pick image' }}
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
                            {{ multiple ? 'Select one or more images for this field.' : 'Select an image to store with the current translation.' }}
                        </p>
                    </div>

                    <BfButton
                        variant="ghost"
                        size="sm"
                        rounded="lg"
                        :disabled="disabled"
                        @click="togglePicker"
                    >
                        {{ multiple ? 'Done' : 'Close' }}
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
                    :selected="isSelected(item)"
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
