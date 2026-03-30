import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { buildTree } from '../utils/buildTree'
import { slugify } from '../utils/slugify'
import { buildDatasetSchemaForm, extractDatasetSchemaConfig, extractDatasetSchemaData } from '../utils/datasetSchema'
import { usePageContextStore } from '@blockforge-cms/editor-sdk'
import {
    createDataset,
    createDatasetCategory,
    createDatasetType,
    deleteDataset,
    deleteDatasetCategory,
    deleteDatasetType,
    fetchDatasetCategories,
    fetchDatasets,
    fetchDatasetTypes,
    syncDatasetCategories,
    updateDatasetCategory,
    updateDataset,
    updateDatasetTranslation,
} from '../utils/datasetApi'

function buildCategoryTree(flatCategories, parentId = null) {
    return buildTree(flatCategories, parentId)
}

function createEmptyTypeForm() {
    return {
        name: '',
        code: '',
        description: '',
    }
}

function createEmptyEntryForm(fields = []) {
    return {
        title: '',
        slug: '',
        visibility_mode: 'disabled',
        visibility_ranges: [],
        ...buildDatasetSchemaForm(fields, {}, {}),
    }
}

function createEmptyCategoryForm(category = null) {
    return {
        name: category?.name ?? '',
        slug: category?.slug ?? '',
    }
}

function toLocalDateTimeInput(value) {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    const pad = (segment) => String(segment).padStart(2, '0')

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function toIsoDateTime(value) {
    if (!value) {
        return null
    }

    const date = new Date(value)

    return Number.isNaN(date.getTime()) ? null : date.toISOString()
}

function normalizeVisibilityRangesForForm(ranges) {
    return Array.isArray(ranges)
        ? ranges.map((range) => ({
            starts_at: toLocalDateTimeInput(range?.starts_at ?? null),
            ends_at: toLocalDateTimeInput(range?.ends_at ?? null),
        }))
        : []
}

function normalizeVisibilityRangesForApi(ranges) {
    return Array.isArray(ranges)
        ? ranges
            .map((range) => ({
                starts_at: toIsoDateTime(range?.starts_at ?? null),
                ends_at: toIsoDateTime(range?.ends_at ?? null),
            }))
            .filter((range) => range.starts_at || range.ends_at)
        : []
}

export const useDatasetsStore = defineStore('datasets', () => {
    const pageContextStore = usePageContextStore()
    const locale = computed(() => pageContextStore.locale || 'en')
    const types = ref([])
    const isLoadingTypes = ref(false)
    const typesError = ref('')
    const categories = ref([])
    const isLoadingCategories = ref(false)
    const categoriesError = ref('')
    const entries = ref([])
    const pagination = ref(null)
    const isLoadingEntries = ref(false)
    const entriesError = ref('')
    const currentPage = ref(1)
    const selectedTypeId = ref(null)
    const selectedCategorySlug = ref(null)
    const visibilityFilter = ref('all')
    const selectedEntryId = ref(null)
    const detailForm = ref(createEmptyEntryForm())
    const isSavingDetail = ref(false)
    const isCreatingEntry = ref(false)
    const createEntryForm = ref(createEmptyEntryForm())
    const isSavingEntry = ref(false)
    const isCreatingType = ref(false)
    const createTypeForm = ref(createEmptyTypeForm())
    const isSavingType = ref(false)
    const editingCategoryId = ref(null)
    const editingCategory = computed(() =>
        categories.value.find((category) => category.id === editingCategoryId.value) ?? null,
    )
    const isCreatingCategory = ref(false)
    const createCategoryParentId = ref(null)
    const categoryDetailForm = ref(createEmptyCategoryForm())
    const isSavingCategory = ref(false)
    const isSavingCategoryDetail = ref(false)
    const hasInitialized = ref(false)

    const selectedType = computed(() =>
        types.value.find((type) => type.id === selectedTypeId.value) ?? null,
    )
    const selectedTypeFields = computed(() => selectedType.value?.schema?.fields ?? [])
    const categoryTree = computed(() => buildCategoryTree(categories.value))
    const selectedEntry = computed(() =>
        entries.value.find((entry) => entry.id === selectedEntryId.value) ?? null,
    )
    const visibilityLabel = computed(() => {
        const labels = {
            all: 'All',
            visible: 'Visible',
            disabled: 'Disabled',
            always: 'Always active',
            scheduled: 'Scheduled',
        }

        return labels[visibilityFilter.value] ?? visibilityFilter.value
    })

    function requestErrorMessage(error, fallback) {
        if (typeof error?.data?.message === 'string' && error.data.message.trim() !== '') {
            return error.data.message.trim()
        }

        if (typeof error?.message === 'string' && error.message.trim() !== '') {
            return error.message.trim()
        }

        return fallback
    }

    function normalizeCategories(categoriesPayload) {
        return Array.isArray(categoriesPayload)
            ? categoriesPayload.map((category) => ({
                id: category.id,
                name: category.name,
                slug: category.slug,
            }))
            : []
    }

    function entryMatchesActiveCategory(entry) {
        if (!selectedCategorySlug.value) {
            return true
        }

        return Array.isArray(entry?.categories)
            && entry.categories.some((category) => category.slug === selectedCategorySlug.value)
    }

    function applyEntryCategories(entryId, categoriesPayload) {
        const normalizedCategories = normalizeCategories(categoriesPayload)
        const nextEntries = []
        let removedFromVisibleList = false

        for (const entry of entries.value) {
            if (entry.id !== entryId) {
                nextEntries.push(entry)
                continue
            }

            const updatedEntry = {
                ...entry,
                categories: normalizedCategories,
            }

            if (entryMatchesActiveCategory(updatedEntry)) {
                nextEntries.push(updatedEntry)
            } else {
                removedFromVisibleList = true
            }
        }

        entries.value = nextEntries

        if (selectedEntryId.value === entryId && removedFromVisibleList) {
            closeSelectedEntry()
        }

        return removedFromVisibleList
    }

    function buildEntryForm(entry = null) {
        if (!entry) {
            return createEmptyEntryForm(selectedTypeFields.value)
        }

        const translation = entry?.translations?.[locale.value] ?? {}

        return {
            title: translation.title ?? '',
            slug: entry.slug ?? '',
            visibility_mode: entry.visibility_mode ?? 'disabled',
            visibility_ranges: normalizeVisibilityRangesForForm(entry.visibility_ranges),
            ...buildDatasetSchemaForm(
                selectedTypeFields.value,
                entry.config ?? {},
                translation.data ?? {},
            ),
        }
    }

    watch(locale, () => {
        if (selectedEntryId.value !== null) {
            detailForm.value = buildEntryForm(selectedEntry.value)
        }
    })

    async function initialize() {
        if (hasInitialized.value) {
            return
        }

        await loadTypes()

        hasInitialized.value = true
    }

    async function loadTypes() {
        isLoadingTypes.value = true
        typesError.value = ''

        try {
            types.value = await fetchDatasetTypes()

            if (selectedTypeId.value !== null && !types.value.some((type) => type.id === selectedTypeId.value)) {
                selectedTypeId.value = null
            }

            if (selectedTypeId.value === null && types.value.length > 0) {
                selectedTypeId.value = types.value[0].id
            }

            if (selectedType.value) {
                await loadCategories()
                await loadEntries(1)
            } else {
                categories.value = []
                categoriesError.value = ''
                entries.value = []
                entriesError.value = ''
                pagination.value = null
            }
        } catch (error) {
            types.value = []
            typesError.value = requestErrorMessage(error, 'Unable to load dataset types.')
            categories.value = []
            categoriesError.value = ''
            entries.value = []
            entriesError.value = ''
            pagination.value = null
        } finally {
            isLoadingTypes.value = false
        }
    }

    async function loadCategories() {
        if (!selectedType.value) {
            categories.value = []
            categoriesError.value = ''
            return
        }

        isLoadingCategories.value = true
        categoriesError.value = ''

        try {
            categories.value = await fetchDatasetCategories(selectedType.value.id)
        } catch (error) {
            categories.value = []
            categoriesError.value = requestErrorMessage(error, 'Unable to load dataset categories.')
        } finally {
            isLoadingCategories.value = false
        }
    }

    async function loadEntries(page = currentPage.value) {
        if (!selectedType.value) {
            entries.value = []
            pagination.value = null
            entriesError.value = ''
            return
        }

        isLoadingEntries.value = true
        entriesError.value = ''
        currentPage.value = page

        try {
            const data = await fetchDatasets({
                typeCode: selectedType.value.code,
                categorySlug: selectedCategorySlug.value,
                visibility: visibilityFilter.value,
                page,
            })

            entries.value = data?.data ?? data ?? []
            pagination.value = data?.meta ?? null
        } catch (error) {
            entries.value = []
            pagination.value = null
            entriesError.value = requestErrorMessage(error, 'Unable to load dataset entries.')
        } finally {
            isLoadingEntries.value = false
        }
    }

    async function selectType(type) {
        selectedTypeId.value = type?.id ?? null
        selectedCategorySlug.value = null
        closeSelectedEntry()
        cancelCreateEntry()
        cancelCreateCategory()
        closeCategorySettings()
        await loadCategories()
        await loadEntries(1)
    }

    async function selectCategory(slug) {
        selectedCategorySlug.value = slug
        closeSelectedEntry()
        cancelCreateEntry()
        await loadEntries(1)
    }

    async function setVisibilityFilter(visibility) {
        visibilityFilter.value = visibility
        await loadEntries(1)
    }

    function selectEntry(entryId) {
        cancelCreateEntry()
        selectedEntryId.value = entryId
        detailForm.value = buildEntryForm(selectedEntry.value)
    }

    function closeSelectedEntry() {
        selectedEntryId.value = null
        detailForm.value = createEmptyEntryForm(selectedTypeFields.value)
    }

    async function saveEntryDetail() {
        if (!selectedEntry.value) {
            return
        }

        isSavingDetail.value = true

        try {
            await updateDatasetTranslation(selectedEntry.value.id, locale.value, {
                title: detailForm.value.title,
                data: extractDatasetSchemaData(selectedTypeFields.value, detailForm.value),
            })
            await updateDataset(selectedEntry.value.id, {
                slug: detailForm.value.slug,
                visibility_mode: detailForm.value.visibility_mode,
                visibility_ranges: normalizeVisibilityRangesForApi(detailForm.value.visibility_ranges),
                config: extractDatasetSchemaConfig(selectedTypeFields.value, detailForm.value),
            })
            await loadEntries(currentPage.value)
            selectEntry(selectedEntry.value.id)
        } finally {
            isSavingDetail.value = false
        }
    }

    function openCreateEntry() {
        closeSelectedEntry()
        isCreatingEntry.value = true
        createEntryForm.value = createEmptyEntryForm(selectedTypeFields.value)
    }

    function cancelCreateEntry() {
        isCreatingEntry.value = false
        createEntryForm.value = createEmptyEntryForm(selectedTypeFields.value)
    }

    function syncCreateEntrySlug() {
        createEntryForm.value.slug = slugify(createEntryForm.value.title)
    }

    async function submitCreateEntry() {
        if (!selectedType.value || !createEntryForm.value.title.trim()) {
            return
        }

        isSavingEntry.value = true

        try {
            const created = await createDataset({
                type_id: selectedType.value.id,
                slug: createEntryForm.value.slug || slugify(createEntryForm.value.title),
                visibility_mode: createEntryForm.value.visibility_mode,
                visibility_ranges: normalizeVisibilityRangesForApi(createEntryForm.value.visibility_ranges),
                config: extractDatasetSchemaConfig(selectedTypeFields.value, createEntryForm.value),
            })

            await updateDatasetTranslation(created.id, locale.value, {
                title: createEntryForm.value.title,
                data: extractDatasetSchemaData(selectedTypeFields.value, createEntryForm.value),
            })

            await loadEntries(1)
            cancelCreateEntry()
            selectEntry(created.id)
        } finally {
            isSavingEntry.value = false
        }
    }

    async function deleteEntryById(entryId) {
        await deleteDataset(entryId)

        if (selectedEntryId.value === entryId) {
            closeSelectedEntry()
        }

        await loadEntries(currentPage.value)
    }

    async function assignEntryCategory(entryId, categoryId) {
        const entry = entries.value.find((candidate) => candidate.id === entryId) ?? null

        if (!entry) {
            return
        }

        const currentCategoryIds = Array.isArray(entry.categories)
            ? entry.categories.map((category) => category.id)
            : []

        if (currentCategoryIds.includes(categoryId)) {
            return
        }

        const updatedCategories = await syncDatasetCategories(entryId, [...currentCategoryIds, categoryId])

        applyEntryCategories(entryId, updatedCategories)
    }

    async function removeEntryCategory(entryId, categoryId) {
        const entry = entries.value.find((candidate) => candidate.id === entryId) ?? null

        if (!entry) {
            return
        }

        const currentCategoryIds = Array.isArray(entry.categories)
            ? entry.categories.map((category) => category.id)
            : []

        if (!currentCategoryIds.includes(categoryId)) {
            return
        }

        const updatedCategories = await syncDatasetCategories(
            entryId,
            currentCategoryIds.filter((id) => id !== categoryId),
        )

        const removedFromVisibleList = applyEntryCategories(entryId, updatedCategories)

        if (removedFromVisibleList) {
            await loadEntries(currentPage.value)
        }
    }

    function openCreateType() {
        isCreatingType.value = true
        createTypeForm.value = createEmptyTypeForm()
    }

    function cancelCreateType() {
        isCreatingType.value = false
        createTypeForm.value = createEmptyTypeForm()
    }

    function syncCreateTypeCode() {
        createTypeForm.value.code = slugify(createTypeForm.value.name)
    }

    async function submitCreateType() {
        if (!createTypeForm.value.name.trim() || !createTypeForm.value.code.trim()) {
            return
        }

        isSavingType.value = true

        try {
            const newType = await createDatasetType({
                name: createTypeForm.value.name,
                code: createTypeForm.value.code,
                description: createTypeForm.value.description || null,
            })

            await loadTypes()
            cancelCreateType()
            await selectType(newType)
        } finally {
            isSavingType.value = false
        }
    }

    async function deleteTypeById(typeId) {
        await deleteDatasetType(typeId)

        if (selectedTypeId.value === typeId) {
            selectedTypeId.value = null
            selectedCategorySlug.value = null
            categories.value = []
            entries.value = []
            pagination.value = null
            closeSelectedEntry()
        }

        await loadTypes()
    }

    function openCreateCategory(parent = null) {
        closeCategorySettings()
        isCreatingCategory.value = true
        createCategoryParentId.value = parent?.id ?? null
        categoryDetailForm.value = createEmptyCategoryForm()
    }

    function cancelCreateCategory() {
        isCreatingCategory.value = false
        createCategoryParentId.value = null
        categoryDetailForm.value = createEmptyCategoryForm()
    }

    function openCategorySettings(categoryId) {
        cancelCreateCategory()
        editingCategoryId.value = categoryId
        categoryDetailForm.value = createEmptyCategoryForm(editingCategory.value)
    }

    function closeCategorySettings() {
        editingCategoryId.value = null
        categoryDetailForm.value = createEmptyCategoryForm()
    }

    function syncCategorySlug() {
        categoryDetailForm.value.slug = slugify(categoryDetailForm.value.name)
    }

    async function submitCreateCategory() {
        const name = categoryDetailForm.value.name.trim()
        const slug = categoryDetailForm.value.slug.trim() || slugify(name)

        if (!selectedType.value || !name || !slug) {
            return
        }

        isSavingCategory.value = true

        try {
            await createDatasetCategory(selectedType.value.id, {
                name,
                slug,
                parent_id: createCategoryParentId.value,
            })

            await loadCategories()
            cancelCreateCategory()
        } finally {
            isSavingCategory.value = false
        }
    }

    async function saveCategoryDetail() {
        if (!editingCategory.value) {
            return
        }

        const currentSlug = editingCategory.value.slug
        const name = categoryDetailForm.value.name.trim()
        const slug = categoryDetailForm.value.slug.trim()

        if (!name || !slug) {
            return
        }

        isSavingCategoryDetail.value = true

        try {
            await updateDatasetCategory(editingCategory.value.id, {
                name,
                slug,
            })

            if (selectedCategorySlug.value === currentSlug) {
                selectedCategorySlug.value = slug
            }

            await loadCategories()
            categoryDetailForm.value = createEmptyCategoryForm(editingCategory.value)
            await loadEntries(1)
        } finally {
            isSavingCategoryDetail.value = false
        }
    }

    async function deleteCategoryById(categoryId, categorySlug) {
        await deleteDatasetCategory(categoryId)

        if (editingCategoryId.value === categoryId) {
            closeCategorySettings()
        }

        if (selectedCategorySlug.value === categorySlug) {
            selectedCategorySlug.value = null
        }

        await loadCategories()
        await loadEntries(1)
    }

    async function moveCategory(categoryId, parentId, sortOrder) {
        await updateDatasetCategory(categoryId, {
            parent_id: parentId,
            sort_order: sortOrder,
        })

        await loadCategories()
    }

    function visibilityTone(entry) {
        return entry?.is_visible_now
            ? 'text-emerald-500'
            : 'text-[var(--bf-ui-muted)]'
    }

    return {
        locale,
        types,
        isLoadingTypes,
        typesError,
        categories,
        isLoadingCategories,
        categoriesError,
        categoryTree,
        entries,
        pagination,
        isLoadingEntries,
        entriesError,
        currentPage,
        selectedTypeId,
        selectedType,
        selectedTypeFields,
        selectedCategorySlug,
        visibilityFilter,
        visibilityLabel,
        selectedEntryId,
        selectedEntry,
        detailForm,
        isSavingDetail,
        isCreatingEntry,
        createEntryForm,
        isSavingEntry,
        isCreatingType,
        createTypeForm,
        isSavingType,
        editingCategory,
        isCreatingCategory,
        createCategoryParentId,
        categoryDetailForm,
        isSavingCategory,
        isSavingCategoryDetail,
        initialize,
        loadTypes,
        loadCategories,
        loadEntries,
        selectType,
        selectCategory,
        setVisibilityFilter,
        selectEntry,
        closeSelectedEntry,
        saveEntryDetail,
        openCreateEntry,
        cancelCreateEntry,
        syncCreateEntrySlug,
        submitCreateEntry,
        deleteEntryById,
        assignEntryCategory,
        removeEntryCategory,
        openCreateType,
        cancelCreateType,
        syncCreateTypeCode,
        submitCreateType,
        deleteTypeById,
        openCreateCategory,
        cancelCreateCategory,
        openCategorySettings,
        closeCategorySettings,
        syncCategorySlug,
        submitCreateCategory,
        saveCategoryDetail,
        deleteCategoryById,
        moveCategory,
        visibilityTone,
    }
})
