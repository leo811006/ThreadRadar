<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite 的 enum 是文字 CHECK 約束，Laravel 遷移改欄位需要 doctrine/dbal
            // 才能重建；此專案未安裝該套件，故 SQLite 環境下不強制約束，交由應用層驗證。
            return;
        }

        DB::statement("ALTER TABLE crawl_logs MODIFY status ENUM('success', 'failed', 'quota_exceeded', 'blocked')");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE crawl_logs MODIFY status ENUM('success', 'failed', 'quota_exceeded')");
    }
};
