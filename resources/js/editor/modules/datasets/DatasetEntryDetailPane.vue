<script setup>
import { computed, ref, watch } from 'vue'
import { BfButton, BfField, BfInput, BfTabs, ExtensionSlot, ModuleFooterBar, ModuleInfoCard, ModuleScrollArea } from '@blockforge-cms/editor-sdk'
import { groupDatasetSchemaFields } from '../../utils/datasetSchema'
import DatasetSchemaFieldRenderer from './DatasetSchemaFieldRenderer.vue'
import DatasetVisibilityEditor from './DatasetVisibilityEditor.vue'

const props = defineProps({
    type: {
        type: Object,
        default: null,
    },
    fields: {
        type: Array,
        default: () => [],
    },
    entry: {
        type: Object,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['save'])

const translatableGroups = computed(() => groupDatasetSchemaFields(props.fields, props.form, true))
const settingsGroups = computed(() => groupDatasetSchemaFields(props.fields, props.form, false))
const schemaStatus = computed(() => props.type?.schema_status ?? 'missing')
const tabs = computed(() => {
    return [
        { value: 'content', label: 'Content' },
        { value: 'settings', label: 'Settings' },
        { value: 'visibility', label: 'Visibility' },
    ]
})
const activeTab = ref('content')

watch(
    () => props.entry?.id,
    () => {
        activeTab.value = 'content'
    },
    { immediate: true },
)

function patchForm(nextForm) {
    Object.assign(props.form, nextForm)
}
</script>

<template>
    <div class="absolute inset-0 flex flex-col">
        <ModuleScrollArea>
            <div class="flex flex-col gap-4 px-3 py-3">
                <div class="grid grid-cols-2 gap-2">
                    <ModuleInfoCard label="Type" value-class="mt-1 truncate text-xs font-medium text-[var(--bf-ui-text)]">
                        {{ type?.name ?? 'Dataset' }}
                    </ModuleInfoCard>
                    <ModuleInfoCard
                        label="Visibility"
                        :value-class="['mt-1 text-xs font-medium', entry?.is_visible_now ? 'text-emerald-500' : 'text-[var(--bf-ui-muted)]']"
                    >
                        {{ entry.visibility_label ?? (entry?.is_visible_now ? 'Visible now' : 'Not visible') }}
                    </ModuleInfoCard>
                </div>

                <BfTabs
                    v-model="activeTab"
                    :items="tabs"
                    full-width
                />

                <ExtensionSlot
                    slot-id="datasets.entry.detail.actions"
                    :context="{ entry, form, type }"
                    class="flex flex-wrap items-center gap-2"
                />

                <div v-if="activeTab === 'content'" class="space-y-4">
                    <BfField label="Title" required>
                        <BfInput
                            v-model="form.title"
                            type="text"
                            placeholder="Entry title"
                        />
                    </BfField>

                    <ModuleInfoCard
                        v-if="schemaStatus !== 'available'"
                        label="Custom fields"
                        value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]"
                    >
                        No schema is registered for this type yet. You can still manage the built-in fields.
                    </ModuleInfoCard>

                    <template v-for="group in translatableGroups" :key="group.key">
                        <ModuleInfoCard
                            v-if="group.label !== 'General'"
                            :label="group.label"
                            value-class="hidden"
                        />
                        <div class="space-y-4">
                            <DatasetSchemaFieldRenderer
                                v-for="field in group.fields"
                                :key="field.name"
                                :field="field"
                                :form="form"
                            />
                        </div>
                    </template>
                </div>

                <div v-else-if="activeTab === 'settings'" class="space-y-4">
                    <BfField label="Slug" required>
                        <BfInput
                            v-model="form.slug"
                            type="text"
                            placeholder="entry-slug"
                            mono
                        />
                    </BfField>

                    <template v-for="group in settingsGroups" :key="group.key">
                        <ModuleInfoCard
                            v-if="group.label !== 'General'"
                            :label="group.label"
                            value-class="hidden"
                        />
                        <div class="space-y-4">
                            <DatasetSchemaFieldRenderer
                                v-for="field in group.fields"
                                :key="field.name"
                                :field="field"
                                :form="form"
                            />
                        </div>
                    </template>
                </div>

                <div v-else class="space-y-4">
                    <DatasetVisibilityEditor
                        :model-value="form"
                        @update:model-value="patchForm"
                    />
                </div>
            </div>
        </ModuleScrollArea>

        <ModuleFooterBar>
            <BfButton
                variant="primary"
                size="md"
                block
                :disabled="isSaving"
                @click="emit('save')"
            >
                {{ isSaving ? 'Saving…' : 'Save entry' }}
            </BfButton>
        </ModuleFooterBar>
    </div>
</template>
