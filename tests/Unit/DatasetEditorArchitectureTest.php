<?php

it('registers the dataset module from the package editor entry and imports shared cms ui through the sdk', function (): void {
    $editorEntry = file_get_contents(dirname(__DIR__, 2).'/resources/js/editor.js');
    $editorCss = file_get_contents(dirname(__DIR__, 2).'/resources/css/editor.css');
    $datasetModule = file_get_contents(dirname(__DIR__, 2).'/resources/js/editor/modules/datasets/DatasetModule.vue');
    $datasetTypeList = file_get_contents(dirname(__DIR__, 2).'/resources/js/editor/modules/datasets/DatasetTypeList.vue');
    $datasetDetailPane = file_get_contents(dirname(__DIR__, 2).'/resources/js/editor/modules/datasets/DatasetEntryDetailPane.vue');
    $datasetEntriesPane = file_get_contents(dirname(__DIR__, 2).'/resources/js/editor/modules/datasets/DatasetEntriesPane.vue');
    $datasetImagePicker = file_get_contents(dirname(__DIR__, 2).'/resources/js/editor/modules/datasets/DatasetImagePicker.vue');
    $datasetStore = file_get_contents(dirname(__DIR__, 2).'/resources/js/editor/stores/datasets.js');
    $datasetApi = file_get_contents(dirname(__DIR__, 2).'/resources/js/editor/utils/datasetApi.js');
    $viteConfig = file_get_contents(dirname(__DIR__, 2).'/vite.config.js');

    expect($editorEntry)->toContain('registerModules({')
        ->toContain("id: 'datasets'")
        ->toContain('component: DatasetModule');

    expect($datasetModule)->toContain("from '@blockforge-cms/editor-sdk'")
        ->toContain("import { useDatasetsStore } from '../../stores/datasets'");

    expect($datasetDetailPane)->toContain('slot-id="datasets.entry.detail.actions"')
        ->toContain("from '@blockforge-cms/editor-sdk'");

    expect($datasetTypeList)->toContain('BfEmptyState')
        ->toContain('Create first type')
        ->toContain('Could not load dataset types')
        ->toContain('Edit type')
        ->toContain('datasetsStore.openEditType(type)');

    expect($datasetEntriesPane)->toContain('Could not load entries')
        ->toContain("defineEmits(['retry', 'select', 'delete', 'remove-category'])");

    expect($datasetImagePicker)->toContain('function openPicker()')
        ->toContain('scrollIntoView')
        ->toContain('@click.stop.prevent="openPicker"');

    expect($datasetStore)->toContain("export const useDatasetsStore = defineStore('datasets'")
        ->toContain("import { usePageContextStore } from '@blockforge-cms/editor-sdk'")
        ->toContain("const typesError = ref('')")
        ->toContain("const entriesError = ref('')")
        ->toContain('selectedTypeId.value === null && types.value.length > 0')
        ->toContain('const editingTypeId = ref(null)')
        ->toContain('function openEditType(type)')
        ->toContain('async function submitTypeForm()');

    expect($datasetApi)->toContain("from '@blockforge-cms/editor-sdk'")
        ->toContain('export async function fetchDatasetTypes')
        ->toContain('export async function updateDatasetType(typeId, payload)');

    expect($editorCss)->toContain("@import 'tailwindcss/utilities';")
        ->toContain("@source '../js/**/*.js';")
        ->toContain("@source '../js/**/*.vue';");

    expect($viteConfig)->toContain('editor-sdk/browser.js')
        ->toContain('editor-sdk/browser-vue.js')
        ->toContain('editor-sdk/browser-pinia.js')
        ->toContain('@tailwindcss/vite')
        ->toContain("editorCss: 'resources/css/editor.css'");
});
