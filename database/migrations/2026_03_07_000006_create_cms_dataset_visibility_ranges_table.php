<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bf_dataset_visibility_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('bf_datasets')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->index(['dataset_id', 'sort_order']);
            $table->index(['dataset_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bf_dataset_visibility_ranges');
    }
};
