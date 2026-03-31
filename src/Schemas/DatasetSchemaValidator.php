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
     * @param  array<string, mixed>  $fieldValues
     * @param  array<string, mixed>  $translatedFieldValues
     * @return array{field_values: array<string, mixed>, translated_field_values: array<string, mixed>}
     */
    public function validate(?DatasetSchema $schema, array $fieldValues, array $translatedFieldValues): array
    {
        if (! $schema instanceof DatasetSchema) {
            return [
                'field_values' => $fieldValues,
                'translated_field_values' => $translatedFieldValues,
            ];
        }

        $merged = NestedData::merge(
            $schema->defaultFieldValues(),
            $schema->extractNonTranslatableData($fieldValues),
        );

        $merged = NestedData::merge(
            $merged,
            $schema->extractTranslatableData($translatedFieldValues),
        );

        $validated = $this->validator->validate($schema->asElementConfig(), $merged);

        return [
            'field_values' => $schema->extractNonTranslatableData($validated),
            'translated_field_values' => $schema->extractTranslatableData($validated),
        ];
    }
}
