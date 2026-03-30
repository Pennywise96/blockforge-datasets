<?php

namespace Blockforge\Datasets\Http\Controllers\Api;

use Blockforge\Datasets\Models\CmsDatasetType;
use Blockforge\Datasets\Schemas\DatasetSchemaRegistry;
use Blockforge\Datasets\Schemas\DatasetSchemaSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetTypeController
{
    public function __construct(
        private readonly DatasetSchemaRegistry $schemaRegistry,
        private readonly DatasetSchemaSerializer $schemaSerializer,
    ) {}

    public function index(): JsonResponse
    {
        $types = CmsDatasetType::query()->orderBy('sort_order')->get();

        return response()->json(
            $types->map(fn (CmsDatasetType $type) => $this->serializeType($type))->values(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:bf_dataset_types,code'],
            'description' => ['nullable', 'string'],
        ]);

        $type = CmsDatasetType::query()->create($validated);

        return response()->json($this->serializeType($type), 201);
    }

    public function update(Request $request, CmsDatasetType $datasetType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:bf_dataset_types,code,'.$datasetType->id],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $datasetType->update($validated);

        return response()->json($this->serializeType($datasetType->fresh()));
    }

    public function destroy(CmsDatasetType $datasetType): JsonResponse
    {
        $datasetType->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeType(CmsDatasetType $type): array
    {
        $schema = $this->schemaRegistry->find($type->code);

        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'description' => $type->description,
            'sort_order' => $type->sort_order,
            'schema_status' => $schema !== null ? 'available' : 'missing',
            'schema' => $schema !== null ? $this->schemaSerializer->serialize($schema) : null,
            'created_at' => $type->created_at,
            'updated_at' => $type->updated_at,
        ];
    }
}
