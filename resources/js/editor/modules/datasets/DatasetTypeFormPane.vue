<script setup>
import { BfButton, BfField, BfInput, BfTextarea, ModuleFooterBar, ModuleInfoCard, ModuleScrollArea } from '@blockforge-cms/editor-sdk'

defineProps({
    mode: {
        type: String,
        default: 'create',
    },
    form: {
        type: Object,
        required: true,
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
    hasSchema: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['save', 'sync-code'])
</script>

<template>
    <div class="absolute inset-0 flex flex-col">
        <ModuleScrollArea>
            <div class="flex flex-col gap-4 px-3 py-3">
                <ModuleInfoCard
                    :label="mode === 'edit' ? 'Edit type' : 'New type'"
                    value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]"
                >
                    <template v-if="mode === 'edit'">
                        Update the editorial metadata for this dataset type. The code should stay stable because schema files are bound to it.
                    </template>
                    <template v-else>
                        Create a dataset type with a stable code. Optional schema files can extend the type with custom fields later.
                    </template>
                </ModuleInfoCard>

                <ModuleInfoCard
                    v-if="mode === 'edit' && hasSchema"
                    label="Schema binding"
                    value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]"
                >
                    This type already has a registered schema. Changing the code also requires updating the matching <code>DatasetSchema::make(...)</code> definition.
                </ModuleInfoCard>

                <BfField label="Name" required>
                    <BfInput
                        v-model="form.name"
                        type="text"
                        placeholder="e.g. Rooms, Treatments"
                        autofocus
                        @keydown.enter="emit('save')"
                    />
                </BfField>

                <BfField label="Code" required>
                    <div class="flex items-center gap-2">
                        <BfInput
                            v-model="form.code"
                            type="text"
                            placeholder="e.g. room, treatment"
                            mono
                            class="min-w-0 flex-1"
                        />
                        <BfButton
                            variant="secondary"
                            size="sm"
                            rounded="lg"
                            class="shrink-0"
                            @click="emit('sync-code')"
                        >
                            Auto
                        </BfButton>
                    </div>
                </BfField>

                <BfField label="Description">
                    <BfTextarea
                        v-model="form.description"
                        rows="4"
                        placeholder="Optional description for editors."
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
                {{ isSaving ? (mode === 'edit' ? 'Saving…' : 'Creating…') : (mode === 'edit' ? 'Save type' : 'Create type') }}
            </BfButton>
        </ModuleFooterBar>
    </div>
</template>
