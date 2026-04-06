<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physical_cards', function (Blueprint $table) {
            if (! Schema::hasColumn('physical_cards', 'card_type')) {
                $table->string('card_type', 32)->default('voucher')->after('tenant_id');
            }

            if (! Schema::hasColumn('physical_cards', 'badge_template_id')) {
                $table->foreignId('badge_template_id')->nullable()->after('voucher_template_id')->constrained('badge_templates')->nullOnDelete();
            }

            if (! Schema::hasColumn('physical_cards', 'holder_type')) {
                $table->string('holder_type', 32)->nullable()->after('badge_template_id');
            }

            if (! Schema::hasColumn('physical_cards', 'holder_id')) {
                $table->unsignedBigInteger('holder_id')->nullable()->after('holder_type');
            }

            $table->index(['tenant_id', 'card_type'], 'physical_cards_tenant_type_idx');
            $table->index(['tenant_id', 'holder_type', 'holder_id'], 'physical_cards_tenant_holder_idx');
        });
    }

    public function down(): void
    {
        Schema::table('physical_cards', function (Blueprint $table) {
            if (Schema::hasColumn('physical_cards', 'badge_template_id')) {
                $table->dropConstrainedForeignId('badge_template_id');
            }

            if (Schema::hasColumn('physical_cards', 'holder_id')) {
                $table->dropIndex('physical_cards_tenant_holder_idx');
                $table->dropColumn('holder_id');
            }

            if (Schema::hasColumn('physical_cards', 'holder_type')) {
                $table->dropColumn('holder_type');
            }

            if (Schema::hasColumn('physical_cards', 'card_type')) {
                $table->dropIndex('physical_cards_tenant_type_idx');
                $table->dropColumn('card_type');
            }
        });
    }
};
