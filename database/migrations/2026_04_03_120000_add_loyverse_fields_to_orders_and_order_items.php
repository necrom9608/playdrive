<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'source')) {
                $table->string('source', 32)->nullable();
            }

            if (! Schema::hasColumn('orders', 'source_reference')) {
                $table->string('source_reference', 191)->nullable();
            }
        });

        if (! $this->indexExists('orders', 'orders_tenant_source_ref_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(
                    ['tenant_id', 'source', 'source_reference'],
                    'orders_tenant_source_ref_idx'
                );
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'legacy_category')) {
                $table->string('legacy_category', 100)->nullable();
            }

            if (! Schema::hasColumn('order_items', 'legacy_article_number')) {
                $table->string('legacy_article_number', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        if ($this->indexExists('orders', 'orders_tenant_source_ref_idx')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_tenant_source_ref_idx');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'source_reference')) {
                $table->dropColumn('source_reference');
            }

            if (Schema::hasColumn('orders', 'source')) {
                $table->dropColumn('source');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'legacy_article_number')) {
                $table->dropColumn('legacy_article_number');
            }

            if (Schema::hasColumn('order_items', 'legacy_category')) {
                $table->dropColumn('legacy_category');
            }
        });
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) as count
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?',
            [$database, $table, $indexName]
        );

        return (int) ($result->count ?? 0) > 0;
    }
};
