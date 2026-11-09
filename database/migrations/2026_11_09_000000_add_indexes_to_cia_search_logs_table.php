<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cia_search_logs', function (Blueprint $table): void {
            $table->string('target_role', 120)->nullable()->after('candidate_id');
            $table->index(['candidate_id', 'created_at'], 'idx_user_created');
            $table->index(['candidate_id', 'target_role'], 'idx_user_role');
        });
    }

    public function down(): void
    {
        Schema::table('cia_search_logs', function (Blueprint $table): void {
            $table->dropIndex('idx_user_created');
            $table->dropIndex('idx_user_role');
            $table->dropColumn('target_role');
        });
    }
};
