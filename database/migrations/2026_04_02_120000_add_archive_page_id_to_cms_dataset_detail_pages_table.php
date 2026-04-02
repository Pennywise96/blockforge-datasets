<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bf_dataset_detail_pages', function (Blueprint $table): void {
            $table->foreignId('archive_page_id')
                ->nullable()
                ->after('page_id')
                ->constrained('bf_pages')
                ->nullOnDelete();
        });

        $mappings = DB::table('bf_dataset_detail_pages')
            ->select(['id', 'page_id'])
            ->get();

        foreach ($mappings as $mapping) {
            $archivePageId = DB::table('bf_pages')
                ->where('id', $mapping->page_id)
                ->value('parent_id');

            DB::table('bf_dataset_detail_pages')
                ->where('id', $mapping->id)
                ->update(['archive_page_id' => $archivePageId]);
        }

        Schema::table('bf_dataset_detail_pages', function (Blueprint $table): void {
            $table->unique(['site_id', 'archive_page_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bf_dataset_detail_pages', function (Blueprint $table): void {
            $table->dropUnique('bf_dataset_detail_pages_site_id_archive_page_id_unique');
            $table->dropConstrainedForeignId('archive_page_id');
        });
    }
};
