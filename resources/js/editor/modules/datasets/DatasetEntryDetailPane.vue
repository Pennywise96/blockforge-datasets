<script setup>
import { ref, watch } from 'vue'
import { BfButton, BfField, BfInput, BfSelect, BfTabs, BfTextarea, ExtensionSlot, ModuleFooterBar, ModuleInfoCard, ModuleScrollArea } from '@blockforge-cms/editor-sdk'
import DatasetImagePicker from './DatasetImagePicker.vue'

const props = defineProps({
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
const activeTab = ref('content')
const detailTabs = [
    { value: 'content', label: 'Content' },
    { value: 'media', label: 'Media' },
    { value: 'publish', label: 'Publish' },
]

function statusTone(status) {
    return status === 'published'
        ? 'text-emerald-500'
        : 'text-[var(--bf-ui-muted)]'
}

watch(
    () => props.entry?.id,
    () => {
        activeTab.value = 'content'
    },
    { immediate: true },
)
</script>

<template>
    <div class="absolute inset-0 flex flex-col">
        <ModuleScrollArea>
            <div class="flex flex-col gap-4 px-3 py-3">
                <div class="grid grid-cols-2 gap-2">
                    <ModuleInfoCard label="Slug" value-class="mt-1 truncate text-xs font-medium text-[var(--bf-ui-text)]">
                        {{ entry.slug }}
                    </ModuleInfoCard>
                    <ModuleInfoCard label="Status" :value-class="['mt-1 text-xs font-medium', statusTone(form.status)]">
                        {{ form.status }}
                    </ModuleInfoCard>
                </div>

                <BfTabs
                    v-model="activeTab"
                    :items="detailTabs"
                    full-width
                />

                <ExtensionSlot
                    slot-id="datasets.entry.detail.actions"
                    :context="{ entry, form }"
                    class="flex flex-wrap items-center gap-2"
                />

                <div v-if="activeTab === 'content'" class="space-y-4">
                    <BfField label="Title">
                        <BfInput
                            v-model="form.title"
                            type="text"
                            placeholder="Entry title"
                        />
                    </BfField>

                    <BfField label="Excerpt">
                        <BfTextarea
                            v-model="form.excerpt"
                            placeholder="Short summary…"
                            rows="3"
                        />
                    </BfField>

                    <BfField label="Content">
                        <BfTextarea
                            v-model="form.content"
                            placeholder="Full content…"
                            rows="6"
                        />
                    </BfField>
                </div>

                <div v-else-if="activeTab === 'media'" class="space-y-4">
                    <BfField label="Image">
                        <DatasetImagePicker
                            v-model="form.image"
                        />
                    </BfField>
                </div>

                <div v-else class="space-y-4">
                    <ModuleInfoCard label="Publishing" value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]">
                        Control the visibility and date metadata for this entry.
                    </ModuleInfoCard>

                    <div class="grid grid-cols-2 gap-2">
                        <BfField label="Date">
                            <BfInput
                                v-model="form.date"
                                type="date"
                            />
                        </BfField>

                        <BfField label="Status">
                            <BfSelect v-model="form.status">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </BfSelect>
                        </BfField>
                    </div>
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
