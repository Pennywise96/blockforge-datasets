import { cloneValue, getPathValue, hasPathValue, setPathValue } from '@blockforge-cms/editor-sdk'

function normalizeFieldList(fields) {
    return Array.isArray(fields) ? fields : []
}

function isSchemaFieldTranslatable(field) {
    return field?.translatable === true
}

function isSchemaFieldVisible(field, form, scopePath = '') {
    const visibleWhen = Array.isArray(field?.visible_when) ? field.visible_when : []
    const hiddenWhen = Array.isArray(field?.hidden_when) ? field.hidden_when : []

    if (visibleWhen.length > 0 && !visibleWhen.every((condition) => conditionMatches(condition, form, scopePath))) {
        return false
    }

    if (hiddenWhen.some((condition) => conditionMatches(condition, form, scopePath))) {
        return false
    }

    return true
}

function conditionMatches(condition, form, scopePath = '') {
    const fieldName = String(condition?.field ?? '')

    if (fieldName === '') {
        return false
    }

    const path = scopePath ? `${scopePath}.${fieldName}` : fieldName
    const dependentValue = getPathValue(form, path)

    if (condition?.operator === 'in') {
        const values = Array.isArray(condition?.values) ? condition.values : []

        if (Array.isArray(dependentValue)) {
            return dependentValue.some((value) => values.includes(value))
        }

        return values.includes(dependentValue)
    }

    return dependentValue === condition?.value
}

function defaultValueForField(field) {
    if (field?.default !== null && field?.default !== undefined) {
        return cloneValue(field.default)
    }

    if (field?.type === 'Checkbox') {
        return false
    }

    if (field?.type === 'Select' && field?.multiple) {
        return []
    }

    if (field?.type === 'Repeater') {
        return []
    }

    return null
}

function extractFields(fields, form, translatable) {
    let extracted = {}

    for (const field of normalizeFieldList(fields)) {
        if (field.type === 'Tabs') {
            for (const tab of field.tabs ?? []) {
                extracted = { ...extracted, ...extractFields(tab.fields ?? [], form, translatable) }
            }

            continue
        }

        if (field.type === 'Fieldset') {
            extracted = { ...extracted, ...extractFields(field.fields ?? [], form, translatable) }
            continue
        }

        if (field.type === 'Repeater') {
            if (translatable) {
                continue
            }

            setPathValue(extracted, field.name, cloneValue(getPathValue(form, field.name, defaultValueForField(field))))
            continue
        }

        if (isSchemaFieldTranslatable(field) !== translatable) {
            continue
        }

        setPathValue(extracted, field.name, cloneValue(getPathValue(form, field.name, defaultValueForField(field))))
    }

    return extracted
}

function applyFields(fields, target, source, translatable) {
    for (const field of normalizeFieldList(fields)) {
        if (field.type === 'Tabs') {
            for (const tab of field.tabs ?? []) {
                applyFields(tab.fields ?? [], target, source, translatable)
            }

            continue
        }

        if (field.type === 'Fieldset') {
            applyFields(field.fields ?? [], target, source, translatable)
            continue
        }

        if (field.type === 'Repeater') {
            if (translatable) {
                continue
            }

            setPathValue(target, field.name, cloneValue(getPathValue(source, field.name, defaultValueForField(field))))
            continue
        }

        if (isSchemaFieldTranslatable(field) !== translatable) {
            continue
        }

        if (hasPathValue(source, field.name)) {
            setPathValue(target, field.name, cloneValue(getPathValue(source, field.name)))
            continue
        }

        setPathValue(target, field.name, cloneValue(defaultValueForField(field)))
    }
}

function containsDisplayField(field, form, translatable, scopePath = '') {
    if (field.type === 'Tabs') {
        return (field.tabs ?? []).some((tab) =>
            (tab.fields ?? []).some((childField) => containsDisplayField(childField, form, translatable, scopePath)),
        )
    }

    if (field.type === 'Fieldset') {
        return (field.fields ?? []).some((childField) => containsDisplayField(childField, form, translatable, scopePath))
    }

    if (!isSchemaFieldVisible(field, form, scopePath)) {
        return false
    }

    if (field.type === 'Repeater') {
        return translatable === false
    }

    return isSchemaFieldTranslatable(field) === translatable
}

export function buildDatasetSchemaForm(fields, configSource = {}, translationSource = {}) {
    const form = {}

    applyFields(fields, form, configSource, false)
    applyFields(fields, form, translationSource, true)

    return form
}

export function extractDatasetSchemaConfig(fields, form) {
    return extractFields(fields, form, false)
}

export function extractDatasetSchemaData(fields, form) {
    return extractFields(fields, form, true)
}

export function groupDatasetSchemaFields(fields, form, translatable) {
    const groups = []
    let generalGroup = null

    for (const field of normalizeFieldList(fields)) {
        if (field.type === 'Tabs') {
            for (const [tabIndex, tab] of (field.tabs ?? []).entries()) {
                const tabFields = (tab.fields ?? []).filter((tabField) => containsDisplayField(tabField, form, translatable))

                if (tabFields.length === 0) {
                    continue
                }

                groups.push({
                    key: `tab:${field.name}:${tab.key ?? tabIndex}`,
                    label: tab.label ?? tab.key ?? `Tab ${tabIndex + 1}`,
                    fields: tabFields,
                })
            }

            continue
        }

        if (!containsDisplayField(field, form, translatable)) {
            continue
        }

        generalGroup ??= {
            key: translatable ? 'tab:content' : 'tab:settings',
            label: 'General',
            fields: [],
        }

        if (!groups.includes(generalGroup)) {
            groups.push(generalGroup)
        }

        generalGroup.fields.push(field)
    }

    return groups
}

export { defaultValueForField, isSchemaFieldVisible }
