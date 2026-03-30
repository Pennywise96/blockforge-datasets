import { registerModules } from '@blockforge-cms/editor-sdk'
import DatasetModule from './editor/modules/datasets/DatasetModule.vue'

registerModules({
    datasets: {
        id: 'datasets',
        label: 'Datasets',
        icon: 'datasets',
        feature: 'datasets',
        defaultWidth: 1100,
        defaultHeight: 680,
        window: {
            role: 'dialog',
            closeOnEscape: true,
            autoFocus: true,
            restoreFocusOnClose: true,
            dismissible: true,
            minHeight: 680,
            syncHeightToContent: false,
        },
        component: DatasetModule,
    },
})
