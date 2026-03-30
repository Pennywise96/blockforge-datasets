<script setup>
import { computed } from 'vue'
import { BfButton, BfCheckbox, BfField, BfIcon, BfInput, BfSelect, BfTextarea, cloneValue, getPathValue, setPathValue } from '@blockforge-cms/editor-sdk'
import { defaultValueForField, isSchemaFieldVisible } from '../../utils/datasetSchema'
import DatasetImagePicker from './DatasetImagePicker.vue'

const props = defineProps({
    field: {
        type: Object,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    basePath: {
        type: String,
        default: '',
    },
    scopePath: {
        type: String,
        default: '',
    },
    inheritedDisabled: {
        type: Boolean,
        default: false,
    },
    inheritedReadOnly: {
        type: Boolean,
        default: false,
    },
})

const actualPath = computed(() => (props.basePath ? `${props.basePath}.${props.field.name}` : props.field.name))

const fieldError = computed(() => {
    const error = props.errors[actualPath.value]

    if (Array.isArray(error)) {
        return error[0] ?? ''
    }

    return error ?? ''
})

const fieldVisible = computed(() => isSchemaFieldVisible(props.field, props.form, props.scopePath))

const fieldDisabled = computed(() => {
    if (props.inheritedDisabled || props.field.disabled) {
        return true
    }

    return (props.field.disabled_when ?? []).some((condition) => conditionMatches(condition))
})

const fieldReadOnly = computed(() => {
    if (props.inheritedReadOnly || props.field.read_only) {
        return true
    }

    return (props.field.read_only_when ?? []).some((condition) => conditionMatches(condition))
})

const repeaterItems = computed(() => {
    const value = fieldValue(actualPath.value, props.field.default ?? [])

    return Array.isArray(value) ? value : []
})

function resolveConditionPath(fieldName) {
    if (!props.scopePath) {
        return fieldName
    }

    return `${props.scopePath}.${fieldName}`
}

function conditionMatches(condition) {
    const dependentValue = getPathValue(props.form, resolveConditionPath(condition.field))

    if (condition.operator === 'in') {
        const values = Array.isArray(condition.values) ? condition.values : []

        if (Array.isArray(dependentValue)) {
            return dependentValue.some((value) => values.includes(value))
        }

        return values.includes(dependentValue)
    }

    return dependentValue === condition.value
}

function fieldValue(path, defaultValue = null) {
    return getPathValue(props.form, path, defaultValue)
}

function updateFieldValue(path, value) {
    setPathValue(props.form, path, value)
}

function updateNumberFieldValue(path, value) {
    updateFieldValue(path, value === '' ? '' : Number(value))
}

function buildRepeaterItemDefaults(fields) {
    const item = {}

    for (const childField of fields ?? []) {
        if (childField.type === 'Fieldset') {
            const nestedDefaults = buildRepeaterItemDefaults(childField.fields ?? [])

            Object.entries(nestedDefaults).forEach(([key, value]) => {
                setPathValue(item, key, value)
            })

            continue
        }

        if (childField.type === 'Tabs') {
            for (const tab of childField.tabs ?? []) {
                const nestedDefaults = buildRepeaterItemDefaults(tab.fields ?? [])

                Object.entries(nestedDefaults).forEach(([key, value]) => {
                    setPathValue(item, key, value)
                })
            }

            continue
        }

        setPathValue(item, childField.name, cloneValue(defaultValueForField(childField)))
    }

    return item
}

function addRepeaterItem() {
    const nextItems = [...repeaterItems.value]
    nextItems.push(buildRepeaterItemDefaults(props.field.fields ?? []))
    updateFieldValue(actualPath.value, nextItems)
}

function removeRepeaterItem(index) {
    const nextItems = [...repeaterItems.value]
    nextItems.splice(index, 1)
    updateFieldValue(actualPath.value, nextItems)
}

function moveRepeaterItem(index, direction) {
    const nextIndex = index + direction

    if (nextIndex < 0 || nextIndex >= repeaterItems.value.length) {
        return
    }

    const nextItems = [...repeaterItems.value]
    ;[nextItems[index], nextItems[nextIndex]] = [nextItems[nextIndex], nextItems[index]]
    updateFieldValue(actualPath.value, nextItems)
}
</script>

<template>
    <div v-if="fieldVisible">
        <BfField
            v-if="field.type === 'Fieldset'"
            :label="field.label"
            :meta="field.meta"
            :help="field.help"
            :hint="field.hint"
            :hint-icon="field.hint_icon"
            :error="fieldError"
        >
            <div class="space-y-4 rounded-[12px] border border-white/[0.08] bg-[#0d1216] p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                <DatasetSchemaFieldRenderer
                    v-for="childField in (field.fields ?? [])"
                    :key="childField.name"
                    :field="childField"
                    :form="form"
                    :errors="errors"
                    :base-path="basePath"
                    :scope-path="scopePath"
                    :inherited-disabled="fieldDisabled"
                    :inherited-read-only="fieldReadOnly"
                />
            </div>
        </BfField>

        <BfField
            v-else-if="field.type === 'Repeater'"
            :label="field.label"
            :meta="field.meta"
            :help="field.help"
            :hint="field.hint"
            :hint-icon="field.hint_icon"
            :error="fieldError"
        >
            <template #label>
                <span class="flex items-center justify-between gap-4">
                    <span class="min-w-0">
                        {{ field.label }}
                    </span>

                    <BfButton
                        size="xs"
                        rounded="lg"
                        :disabled="fieldDisabled || fieldReadOnly || (field.max_items !== null && repeaterItems.length >= field.max_items)"
                        @click="addRepeaterItem"
                    >
                        {{ field.add_button_label || 'Add item' }}
                    </BfButton>
                </span>
            </template>

            <div class="space-y-3">
                <div
                    v-for="(item, index) in repeaterItems"
                    :key="`${actualPath}.${index}`"
                    class="space-y-4 rounded-[12px] border border-white/[0.08] bg-[#0d1216] p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] font-medium text-white/42">
                            {{ field.item_label || 'Item' }} {{ index + 1 }}
                        </p>

                        <div class="flex items-center gap-1">
                            <BfButton
                                size="icon-sm"
                                variant="ghost"
                                rounded="lg"
                                :disabled="fieldDisabled || fieldReadOnly || index === 0"
                                title="Move up"
                                @click="moveRepeaterItem(index, -1)"
                            >
                                <BfIcon name="chevron-up" class="h-3 w-3" />
                            </BfButton>
                            <BfButton
                                size="icon-sm"
                                variant="ghost"
                                rounded="lg"
                                :disabled="fieldDisabled || fieldReadOnly || index === repeaterItems.length - 1"
                                title="Move down"
                                @click="moveRepeaterItem(index, 1)"
                            >
                                <BfIcon name="chevron-down" class="h-3 w-3" />
                            </BfButton>
                            <BfButton
                                size="icon-sm"
                                variant="danger-text"
                                rounded="lg"
                                :disabled="fieldDisabled || fieldReadOnly || (field.min_items !== null && repeaterItems.length <= field.min_items)"
                                title="Remove item"
                                @click="removeRepeaterItem(index)"
                            >
                                <BfIcon name="delete" class="h-3 w-3" />
                            </BfButton>
                        </div>
                    </div>

                    <DatasetSchemaFieldRenderer
                        v-for="childField in (field.fields ?? [])"
                        :key="`${actualPath}.${index}.${childField.name}`"
                        :field="childField"
                        :form="form"
                        :errors="errors"
                        :base-path="`${actualPath}.${index}`"
                        :scope-path="`${actualPath}.${index}`"
                        :inherited-disabled="fieldDisabled"
                        :inherited-read-only="fieldReadOnly"
                    />
                </div>

                <div
                    v-if="repeaterItems.length === 0"
                    class="rounded-[12px] border border-dashed border-white/[0.08] px-3 py-4 text-[11px] text-white/42"
                >
                    No items yet.
                </div>
            </div>
        </BfField>

        <BfField
            v-else-if="field.type === 'Checkbox'"
            :meta="field.meta"
            :help="field.help"
            :hint="field.hint"
            :hint-icon="field.hint_icon"
            :error="fieldError"
            :required="field.required"
        >
            <template #label>
                <span class="flex items-center justify-between gap-4">
                    <span class="min-w-0">
                        {{ field.label }}
                    </span>

                    <BfCheckbox
                        :model-value="fieldValue(actualPath, false)"
                        :show-status-label="false"
                        :disabled="fieldDisabled || fieldReadOnly"
                        class="shrink-0"
                        @update:model-value="updateFieldValue(actualPath, $event)"
                    />
                </span>
            </template>
        </BfField>

        <BfField
            v-else
            :label="field.label"
            :meta="field.meta"
            :help="field.help"
            :hint="field.hint"
            :hint-icon="field.hint_icon"
            :error="fieldError"
            :required="field.required"
        >
            <BfSelect
                v-if="field.type === 'Select'"
                :model-value="fieldValue(actualPath)"
                :multiple="field.multiple ?? false"
                :disabled="fieldDisabled || fieldReadOnly"
                @update:model-value="updateFieldValue(actualPath, $event)"
            >
                <option
                    v-if="field.placeholder && !(field.multiple ?? false) && !fieldValue(actualPath)"
                    value=""
                    disabled
                >{{ field.placeholder }}</option>
                <option
                    v-for="(label, value) in (field.options ?? {})"
                    :key="value"
                    :value="value"
                >{{ label }}</option>
            </BfSelect>

            <DatasetImagePicker
                v-else-if="field.type === 'PictureField'"
                :model-value="fieldValue(actualPath)"
                :multiple="field.multiple ?? false"
                :disabled="fieldDisabled || fieldReadOnly"
                @update:model-value="updateFieldValue(actualPath, $event)"
            />

            <BfTextarea
                v-else-if="field.type === 'RichTextInput'"
                :model-value="fieldValue(actualPath)"
                :placeholder="field.placeholder"
                :rows="field.rows ?? 4"
                :maxlength="field.max_length"
                :readonly="fieldReadOnly"
                :disabled="fieldDisabled"
                @update:model-value="updateFieldValue(actualPath, $event)"
            />

            <BfInput
                v-else-if="field.type === 'NumberInput'"
                :model-value="fieldValue(actualPath)"
                type="number"
                :placeholder="field.placeholder"
                :min="field.min"
                :max="field.max"
                :step="field.step"
                :readonly="fieldReadOnly"
                :disabled="fieldDisabled"
                @update:model-value="updateNumberFieldValue(actualPath, $event)"
            />

            <BfInput
                v-else
                :model-value="fieldValue(actualPath)"
                type="text"
                :placeholder="field.placeholder"
                :maxlength="field.max_length"
                :readonly="fieldReadOnly"
                :disabled="fieldDisabled"
                @update:model-value="updateFieldValue(actualPath, $event)"
            />
        </BfField>
    </div>
</template>
