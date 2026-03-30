<script setup>
import { ref } from 'vue'
import { BfButton, BfField, BfInput, ModuleFooterBar, ModuleInfoCard, ModuleScrollArea } from '@blockforge-cms/editor-sdk'

const props = defineProps({
    isCreating: {
        type: Boolean,
        default: false,
    },
    createParentCategoryName: {
        type: String,
        default: null,
    },
    editingCategoryName: {
        type: String,
        default: null,
    },
    form: {
        type: Object,
        required: true,
    },
    isSavingCreate: {
        type: Boolean,
        default: false,
    },
    isSavingDetail: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['save', 'delete', 'sync-slug'])
const confirmingDelete = ref(false)

function onDelete() {
    emit('delete')
    confirmingDelete.value = false
}
</script>

<template>
    <div class="absolute inset-0 flex flex-col">
        <ModuleScrollArea>
            <div class="flex flex-col gap-4 px-3 py-3">
                <ModuleInfoCard
                    :label="isCreating ? (createParentCategoryName ? 'Parent category' : 'Creating') : 'Selected category'"
                    value-class="mt-1 truncate text-xs font-medium text-[var(--bf-ui-text)]"
                >
                    {{ isCreating ? (createParentCategoryName ?? 'Root category') : editingCategoryName }}
                </ModuleInfoCard>

                <BfField label="Name">
                    <BfInput
                        v-model="form.name"
                        type="text"
                        placeholder="Category name"
                    />
                </BfField>

                <BfField label="Slug">
                    <div class="flex items-center gap-2">
                        <BfInput
                            v-model="form.slug"
                            type="text"
                            placeholder="category-slug"
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

                <ModuleInfoCard label="Used for" value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]">
                    {{ isCreating
                        ? 'Create a folder for grouping dataset entries. Sub-categories stay nested under the selected parent.'
                        : 'Filtering dataset entries in this rail and grouping related records for editors.' }}
                </ModuleInfoCard>
            </div>
        </ModuleScrollArea>

        <ModuleFooterBar class="flex flex-col gap-2">
            <Transition name="confirm-delete">
                <div v-if="confirmingDelete" class="flex gap-1.5">
                    <BfButton
                        variant="secondary"
                        size="sm"
                        rounded="lg"
                        class="flex-1"
                        @click="confirmingDelete = false"
                    >
                        Cancel
                    </BfButton>
                    <BfButton
                        variant="danger"
                        size="sm"
                        rounded="lg"
                        class="flex-1"
                        @click="onDelete"
                    >
                        Confirm delete
                    </BfButton>
                </div>
                <BfButton
                    v-else-if="!isCreating"
                    variant="danger-text"
                    size="sm"
                    rounded="lg"
                    block
                    @click="confirmingDelete = true"
                >
                    Delete category
                </BfButton>
            </Transition>

            <BfButton
                variant="primary"
                size="md"
                block
                :disabled="isCreating ? isSavingCreate : isSavingDetail"
                @click="emit('save')"
            >
                {{ isCreating
                    ? (isSavingCreate ? 'Creating…' : 'Create category')
                    : (isSavingDetail ? 'Saving…' : 'Save category') }}
            </BfButton>
        </ModuleFooterBar>
    </div>
</template>

<style scoped>
@layer cms-editor {
.confirm-delete-enter-active,
.confirm-delete-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.confirm-delete-enter-from,
.confirm-delete-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
}
</style>
