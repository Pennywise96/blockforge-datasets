<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_dataset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('cms_dataset_types')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_dataset_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_dataset_categories');
    }
};
