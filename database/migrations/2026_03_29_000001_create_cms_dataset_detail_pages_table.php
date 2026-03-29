<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bf_dataset_detail_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('bf_sites')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('bf_pages')->cascadeOnDelete();
            $table->foreignId('dataset_type_id')->constrained('bf_dataset_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('page_id');
            $table->unique(['site_id', 'dataset_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bf_dataset_detail_pages');
    }
};
