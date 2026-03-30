<script setup>
import { BfButton, BfField, BfInput, BfSelect, ModuleFooterBar, ModuleInfoCard, ModuleScrollArea } from '@blockforge-cms/editor-sdk'

defineProps({
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
</script>

<template>
    <div class="absolute inset-0 flex flex-col">
        <ModuleScrollArea>
            <div class="flex flex-col gap-4 px-3 py-3">
                <ModuleInfoCard label="New entry" value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]">
                    Create a new record for the selected dataset type and publish it when it is ready.
                </ModuleInfoCard>

                <BfField label="Title">
                    <BfInput
                        v-model="form.title"
                        type="text"
                        placeholder="Entry title"
                        autofocus
                        @keydown.enter="emit('save')"
                    />
                </BfField>

                <BfField label="Slug">
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
