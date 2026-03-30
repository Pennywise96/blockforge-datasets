<script setup>
import { BfButton, BfField, BfInput, ModuleFooterBar, ModuleInfoCard, ModuleScrollArea } from '@blockforge-cms/editor-sdk'

defineProps({
    name: {
        type: String,
        default: '',
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:name', 'save'])
</script>

<template>
    <div class="absolute inset-0 flex flex-col">
        <ModuleScrollArea>
            <div class="flex flex-col gap-4 px-3 py-3">
                <ModuleInfoCard label="New type" value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]">
                    Create a top-level dataset type like blog, treatments, events, or news.
                </ModuleInfoCard>

                <BfField label="Name">
                    <BfInput
                        :model-value="name"
                        type="text"
                        placeholder="e.g. Blog, Treatments"
                        autofocus
                        @update:model-value="emit('update:name', $event)"
                        @keydown.enter="emit('save')"
                    />
                </BfField>
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
                {{ isSaving ? 'Creating…' : 'Create type' }}
            </BfButton>
        </ModuleFooterBar>
    </div>
</template>
