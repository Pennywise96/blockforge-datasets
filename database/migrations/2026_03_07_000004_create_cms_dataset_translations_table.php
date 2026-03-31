<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bf_dataset_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('bf_datasets')->cascadeOnDelete();
            $table->string('locale');
            $table->string('title');
            $table->json('field_values')->nullable();
            $table->timestamps();

            $table->unique(['dataset_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bf_dataset_translations');
    }
};
