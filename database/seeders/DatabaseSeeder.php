<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * 执行数据库种子数据写入。
     *
     * 当前 Phase 1 不通过 Seeder 初始化业务数据，避免绕开 database/schema.sql 这份数据库标准。
     */
    public function run(): void
    {
        // Phase 1 不通过 seeder 写入业务数据。
        // 当前数据库结构和初始化数据都应先以 database/schema.sql 和脱敏样例为准。
    }
}
