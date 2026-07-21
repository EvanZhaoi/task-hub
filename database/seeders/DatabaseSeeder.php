<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Phase 1 不通过 seeder 写入业务数据。
        // 当前数据库结构和初始化数据都应先以 database/schema.sql 和脱敏样例为准。
    }
}
