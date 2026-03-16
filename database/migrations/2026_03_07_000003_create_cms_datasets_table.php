<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('cms_dataset_types')->cascadeOnDelete();
            $table->string('slug');
            $table->date('date')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_datasets');
    }
};
