<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bf_dataset_types', function (Blueprint $table) {
            $table->renameColumn('slug', 'code');
        });

        Schema::table('bf_datasets', function (Blueprint $table) {
            $table->string('visibility_mode')->nullable()->after('slug');
        });

        Schema::create('bf_dataset_visibility_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('bf_datasets')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
        });

        $datasetModel = new class extends Model
        {
            protected $table = 'bf_datasets';

            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'date' => 'date',
                    'config' => 'array',
                ];
            }
        };

        $datasetModel->newQuery()->orderBy('id')->chunkById(100, function ($datasets): void {
            foreach ($datasets as $dataset) {
                $config = is_array($dataset->config) ? $dataset->config : [];

                if ($dataset->date !== null && ! array_key_exists('_legacy_date', $config)) {
                    $config['_legacy_date'] = $dataset->date->toDateString();
                }

                $dataset->forceFill([
                    'config' => $config,
                    'visibility_mode' => $dataset->status === 'published' ? 'always' : 'disabled',
                ])->saveQuietly();
            }
        });

        $translationModel = new class extends Model
        {
            protected $table = 'bf_dataset_translations';

            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'data' => 'array',
                ];
            }
        };

        $translationModel->newQuery()->orderBy('id')->chunkById(100, function ($translations): void {
            foreach ($translations as $translation) {
                $data = is_array($translation->data) ? $translation->data : [];

                if ($translation->excerpt !== null && ! array_key_exists('excerpt', $data)) {
                    $data['excerpt'] = $translation->excerpt;
                }

                if ($translation->content !== null && ! array_key_exists('content', $data)) {
                    $data['content'] = $translation->content;
                }

                $translation->forceFill([
                    'data' => $data,
                ])->saveQuietly();
            }
        });

        Schema::table('bf_datasets', function (Blueprint $table) {
            $table->string('visibility_mode')->default('disabled')->nullable(false)->change();
            $table->dropColumn(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('bf_datasets', function (Blueprint $table) {
            $table->date('date')->nullable()->after('slug');
            $table->enum('status', ['draft', 'published'])->default('draft')->after('date');
        });

        $datasetModel = new class extends Model
        {
            protected $table = 'bf_datasets';

            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'config' => 'array',
                ];
            }
        };

        $datasetModel->newQuery()->orderBy('id')->chunkById(100, function ($datasets): void {
            foreach ($datasets as $dataset) {
                $config = is_array($dataset->config) ? $dataset->config : [];
                $legacyDate = is_string($config['_legacy_date'] ?? null) ? $config['_legacy_date'] : null;

                unset($config['_legacy_date']);

                $dataset->forceFill([
                    'config' => $config,
                    'date' => $legacyDate,
                    'status' => $dataset->visibility_mode === 'disabled' ? 'draft' : 'published',
                ])->saveQuietly();
            }
        });

        $translationModel = new class extends Model
        {
            protected $table = 'bf_dataset_translations';

            protected $guarded = [];

            protected function casts(): array
            {
                return [
                    'data' => 'array',
                ];
            }
        };

        $translationModel->newQuery()->orderBy('id')->chunkById(100, function ($translations): void {
            foreach ($translations as $translation) {
                $data = is_array($translation->data) ? $translation->data : [];

                $translation->forceFill([
                    'excerpt' => is_string($data['excerpt'] ?? null) ? $data['excerpt'] : $translation->excerpt,
                    'content' => is_string($data['content'] ?? null) ? $data['content'] : $translation->content,
                ])->saveQuietly();
            }
        });

        Schema::dropIfExists('bf_dataset_visibility_ranges');

        Schema::table('bf_datasets', function (Blueprint $table) {
            $table->dropColumn('visibility_mode');
        });

        Schema::table('bf_dataset_types', function (Blueprint $table) {
            $table->renameColumn('code', 'slug');
        });
    }
};
