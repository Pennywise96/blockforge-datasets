<?php

namespace Blockforge\Datasets\Schemas;

use Blockforge\Cms\Elements\ElementConfig;
use Blockforge\Cms\Fields\Field;
use Blockforge\Cms\Fields\Fieldset;
use Blockforge\Cms\Fields\PictureField;
use Blockforge\Cms\Fields\Repeater;
use Blockforge\Cms\Fields\Tabs;
use InvalidArgumentException;

class DatasetSchema
{
    private string $code;

    /** @var Field[] */
    private array $fields = [];

    public static function make(string $code): static
    {
        $schema = new static;
        $schema->code = trim($code);

        return $schema;
    }

    /**
     * @param  Field[]  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param  string[]  $reservedPaths
     */
    public function validateFieldDefinitions(array $reservedPaths = []): void
    {
        if ($this->code === '') {
            throw new InvalidArgumentException('Dataset schema code must not be empty.');
        }

        $this->asElementConfig()->validateFieldDefinitions();

        foreach ($this->flattenFields($this->fields) as $field) {
            $path = $field->getName();

            foreach ($reservedPaths as $reservedPath) {
                if (
                    $path === $reservedPath
                    || str_starts_with($path, $reservedPath.'.')
                    || str_starts_with($reservedPath, $path.'.')
                ) {
                    throw new InvalidArgumentException(
                        'Dataset schema "'.$this->code.'" contains reserved field path "'.$path.'".',
                    );
                }
            }
        }

        $this->assertNoTranslatableFieldsInsideRepeaters($this->fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return $this->asElementConfig()->defaultConfig();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultTranslationData(): array
    {
        return $this->asElementConfig()->defaultTranslationData();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractNonTranslatableData(array $data): array
    {
        return $this->asElementConfig()->extractNonTranslatableData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractTranslatableData(array $data): array
    {
        return $this->asElementConfig()->extractTranslatableData($data);
    }

    /**
     * @return array<string, array{multiple: bool, translatable: bool}>
     */
    public function pictureFields(): array
    {
        $fields = [];

        foreach ($this->leafFields() as $field) {
            if (! $field instanceof PictureField) {
                continue;
            }

            $fields[$field->getName()] = [
                'multiple' => $field->isMultiple(),
                'translatable' => $field->isTranslatable(),
            ];
        }

        return $fields;
    }

    public function asElementConfig(): ElementConfig
    {
        return ElementConfig::make('dataset:'.$this->code)->fields($this->fields);
    }

    /**
     * @param  Field[]  $fields
     * @return Field[]
     */
    public function leafFields(): array
    {
        return $this->flattenFields($this->fields);
    }

    /**
     * @param  Field[]  $fields
     * @return Field[]
     */
    private function flattenFields(array $fields): array
    {
        $flattened = [];

        foreach ($fields as $field) {
            if ($field instanceof Tabs) {
                foreach ($field->getTabs() as $tab) {
                    array_push($flattened, ...$this->flattenFields($tab->getFields()));
                }

                continue;
            }

            if ($field instanceof Fieldset) {
                array_push($flattened, ...$this->flattenFields($field->getFields()));

                continue;
            }

            if ($field instanceof Repeater) {
                $flattened[] = $field;

                continue;
            }

            $flattened[] = $field;
        }

        return $flattened;
    }

    /**
     * @param  Field[]  $fields
     */
    private function assertNoTranslatableFieldsInsideRepeaters(array $fields, bool $insideRepeater = false): void
    {
        foreach ($fields as $field) {
            if ($field instanceof Tabs) {
                foreach ($field->getTabs() as $tab) {
                    $this->assertNoTranslatableFieldsInsideRepeaters($tab->getFields(), $insideRepeater);
                }

                continue;
            }

            if ($field instanceof Fieldset) {
                $this->assertNoTranslatableFieldsInsideRepeaters($field->getFields(), $insideRepeater);

                continue;
            }

            if ($field instanceof Repeater) {
                $this->assertNoTranslatableFieldsInsideRepeaters($field->getFields(), true);

                continue;
            }

            if ($insideRepeater && $field->isTranslatable()) {
                throw new InvalidArgumentException(
                    'Dataset schema "'.$this->code.'" contains translatable repeater field "'.$field->getName().'".',
                );
            }
        }
    }
}
