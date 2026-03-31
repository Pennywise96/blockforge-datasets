<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bf_datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('bf_dataset_types')->cascadeOnDelete();
            $table->string('slug');
            $table->string('visibility_mode')->default('disabled');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('field_values')->nullable();
            $table->timestamps();

            $table->unique(['type_id', 'slug']);
            $table->index(['type_id', 'created_at']);
            $table->index(['type_id', 'visibility_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bf_datasets');
    }
};
