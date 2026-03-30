<script setup>
import { computed } from 'vue'
import { BfButton, BfField, BfInput, ModuleFooterBar, ModuleInfoCard, ModuleScrollArea } from '@blockforge-cms/editor-sdk'
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
    form: {
        type: Object,
        required: true,
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['save', 'sync-slug'])

const translatableGroups = computed(() => groupDatasetSchemaFields(props.fields, props.form, true))
const settingsGroups = computed(() => groupDatasetSchemaFields(props.fields, props.form, false))

function patchForm(nextForm) {
    Object.assign(props.form, nextForm)
}
</script>

<template>
    <div class="absolute inset-0 flex flex-col">
        <ModuleScrollArea>
            <div class="flex flex-col gap-4 px-3 py-3">
                <ModuleInfoCard label="New entry" value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]">
                    Create a new record for {{ type?.name ?? 'this dataset type' }} and define its visibility before publishing.
                </ModuleInfoCard>

                <BfField label="Title" required>
                    <BfInput
                        v-model="form.title"
                        type="text"
                        placeholder="Entry title"
                        autofocus
                        @keydown.enter="emit('save')"
                    />
                </BfField>

                <BfField label="Slug" required>
                    <div class="flex items-center gap-2">
                        <BfInput
                            v-model="form.slug"
                            type="text"
                            placeholder="entry-slug"
                            mono
                            class="min-w-0 flex-1"
                        />
                        <BfButton
                            variant="secondary"
                            size="sm"
                            rounded="lg"
                            class="shrink-0"
                            @click="emit('sync-slug')"
                        >
                            Auto
                        </BfButton>
                    </div>
                </BfField>

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

                <template v-for="group in settingsGroups" :key="`settings-${group.key}`">
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

                <DatasetVisibilityEditor
                    :model-value="form"
                    @update:model-value="patchForm"
                />
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
                {{ isSaving ? 'Creating…' : 'Create entry' }}
            </BfButton>
        </ModuleFooterBar>
    </div>
</template>
