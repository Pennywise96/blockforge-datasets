<?php

namespace Blockforge\Datasets\Schemas;

use Blockforge\Cms\Elements\ElementConfigValidator;
use Blockforge\Cms\Support\NestedData;

class DatasetSchemaValidator
{
    public function __construct(
        private readonly ElementConfigValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $translationData
     * @return array{config: array<string, mixed>, data: array<string, mixed>}
     */
    public function validate(?DatasetSchema $schema, array $config, array $translationData): array
    {
        if (! $schema instanceof DatasetSchema) {
            return [
                'config' => $config,
                'data' => $translationData,
            ];
        }

        $merged = NestedData::merge(
            $schema->defaultConfig(),
            $schema->extractNonTranslatableData($config),
        );

        $merged = NestedData::merge(
            $merged,
            $schema->extractTranslatableData($translationData),
        );

        $validated = $this->validator->validate($schema->asElementConfig(), $merged);

        return [
            'config' => $schema->extractNonTranslatableData($validated),
            'data' => $schema->extractTranslatableData($validated),
        ];
    }
}
