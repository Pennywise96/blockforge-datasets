<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bf_dataset_category_dataset', function (Blueprint $table) {
            $table->foreignId('dataset_id')->constrained('bf_datasets')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('bf_dataset_categories')->cascadeOnDelete();

            $table->primary(['dataset_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bf_dataset_category_dataset');
    }
};
