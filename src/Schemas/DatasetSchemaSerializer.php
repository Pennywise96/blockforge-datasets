<?php

namespace Blockforge\Datasets\Schemas;

use Blockforge\Cms\Elements\ElementSchemaSerializer;
use Blockforge\Cms\Fields\Field;

class DatasetSchemaSerializer
{
    public function __construct(
        private readonly ElementSchemaSerializer $serializer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function serialize(DatasetSchema $schema): array
    {
        return [
            'code' => $schema->getCode(),
            'fields' => collect($schema->getFields())
                ->map(fn (Field $field) => $this->serializer->serializeField($field))
                ->values()
                ->all(),
        ];
    }
}
