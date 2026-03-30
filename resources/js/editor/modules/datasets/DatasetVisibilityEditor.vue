<script setup>
import { computed } from 'vue'
import { BfButton, BfField, BfInput, BfSelect, ModuleInfoCard } from '@blockforge-cms/editor-sdk'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['update:modelValue'])

const mode = computed({
    get: () => props.modelValue.visibility_mode ?? 'disabled',
    set: (value) => updateForm({ visibility_mode: value }),
})

const ranges = computed(() => (
    Array.isArray(props.modelValue.visibility_ranges)
        ? props.modelValue.visibility_ranges
        : []
))

function updateForm(patch) {
    emit('update:modelValue', {
        ...props.modelValue,
        ...patch,
    })
}

function updateRanges(nextRanges) {
    updateForm({ visibility_ranges: nextRanges })
}

function addRange() {
    updateRanges([
        ...ranges.value,
        {
            starts_at: '',
            ends_at: '',
        },
    ])
}

function updateRange(index, key, value) {
    const nextRanges = [...ranges.value]
    nextRanges[index] = {
        ...nextRanges[index],
        [key]: value,
    }

    updateRanges(nextRanges)
}

function removeRange(index) {
    const nextRanges = [...ranges.value]
    nextRanges.splice(index, 1)
    updateRanges(nextRanges)
}
</script>

<template>
    <div class="space-y-4">
        <ModuleInfoCard label="Visibility" value-class="mt-1 text-xs leading-relaxed text-[var(--bf-ui-text)]">
            Control whether the entry is disabled, always active, or active only during scheduled windows.
        </ModuleInfoCard>

        <BfField label="Mode">
            <BfSelect v-model="mode">
                <option value="disabled">Disabled</option>
                <option value="always">Always active</option>
                <option value="scheduled">Scheduled</option>
            </BfSelect>
        </BfField>

        <div v-if="mode === 'scheduled'" class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-medium text-[var(--bf-ui-text)]">Active ranges</p>

                <BfButton variant="secondary" size="sm" rounded="lg" @click="addRange">
                    Add range
                </BfButton>
            </div>

            <div v-if="ranges.length === 0" class="rounded-[12px] border border-dashed border-[var(--bf-ui-border)] px-3 py-4 text-[11px] text-[var(--bf-ui-muted)]">
                No ranges yet. Add one or more active windows.
            </div>

            <div
                v-for="(range, index) in ranges"
                :key="`range-${index}`"
                class="space-y-3 rounded-[12px] border border-[var(--bf-ui-border)] bg-[var(--bf-ui-panel-soft)] p-3"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[11px] font-medium text-[var(--bf-ui-text)]">Range {{ index + 1 }}</p>

                    <BfButton variant="ghost" size="sm" rounded="lg" @click="removeRange(index)">
                        Remove
                    </BfButton>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <BfField label="Starts at">
                        <BfInput
                            :model-value="range.starts_at ?? ''"
                            type="datetime-local"
                            @update:model-value="updateRange(index, 'starts_at', $event)"
                        />
                    </BfField>

                    <BfField label="Ends at">
                        <BfInput
                            :model-value="range.ends_at ?? ''"
                            type="datetime-local"
                            @update:model-value="updateRange(index, 'ends_at', $event)"
                        />
                    </BfField>
                </div>
            </div>
        </div>
    </div>
</template>
