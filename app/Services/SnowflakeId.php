<?php

namespace App\Services;

/**
 * TaskHub 单体版雪花 ID 生成器。
 *
 * 当前数据库表使用 BIGINT UNSIGNED 主键，但项目暂未接入公司统一 ID 服务。
 * TaskHub 短期内是单体 Laravel 应用，不需要 workerId 区分不同服务器。
 * 因此这里采用“毫秒时间戳 + 同毫秒内序号”的简化实现。
 */
class SnowflakeId
{
    private const MAX_SEQUENCE = 1000;

    private const MILLIS_MULTIPLIER = 1000;

    private int $lastMillisecond = 0;

    private int $sequence = 0;

    /**
     * 生成下一个业务主键 ID。
     *
     * ID 结构为：当前毫秒时间戳 * 1000 + 同毫秒内自增序号。
     * 这样可以在单体应用内得到递增 BIGINT，满足当前 schema.sql 中非自增主键的写入要求。
     */
    public function next(): int
    {
        $millisecond = $this->currentMillis();

        if ($millisecond < $this->lastMillisecond) {
            // 如果系统时间短暂回拨，沿用上一次毫秒值，保证单进程内 ID 不倒退。
            $millisecond = $this->lastMillisecond;
        }

        if ($millisecond === $this->lastMillisecond) {
            // 同一毫秒内最多生成 1000 个 ID，超过后等待下一毫秒。
            $this->sequence = ($this->sequence + 1) % self::MAX_SEQUENCE;

            if ($this->sequence === 0) {
                $millisecond = $this->waitNextMillisecond($millisecond);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastMillisecond = $millisecond;

        return ($millisecond * self::MILLIS_MULTIPLIER) + $this->sequence;
    }

    /**
     * 获取当前 Unix 毫秒时间戳。
     *
     * microtime(true) 返回秒级浮点数，这里乘以 1000 后向下取整，作为 ID 的时间部分。
     */
    private function currentMillis(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /**
     * 等待系统时间进入下一个毫秒。
     *
     * 当同一毫秒内生成超过 1000 个 ID 时，为避免 sequence 回绕导致重复 ID，需要阻塞到下一毫秒。
     */
    private function waitNextMillisecond(int $currentMillisecond): int
    {
        do {
            $nextMillisecond = $this->currentMillis();
        } while ($nextMillisecond <= $currentMillisecond);

        return $nextMillisecond;
    }
}
