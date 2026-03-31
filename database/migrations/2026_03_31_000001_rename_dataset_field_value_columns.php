<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bf_datasets', function (Blueprint $table) {
            $table->renameColumn('config', 'field_values');
        });

        Schema::table('bf_dataset_translations', function (Blueprint $table) {
            $table->renameColumn('data', 'field_values');
        });
    }

    public function down(): void
    {
        Schema::table('bf_dataset_translations', function (Blueprint $table) {
            $table->renameColumn('field_values', 'data');
        });

        Schema::table('bf_datasets', function (Blueprint $table) {
            $table->renameColumn('field_values', 'config');
        });
    }
};
