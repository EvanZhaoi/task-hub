<?php

namespace App\Services;

/**
 * TaskHub 本地雪花 ID 生成器。
 *
 * 当前数据库表使用 BIGINT UNSIGNED 主键，但项目暂未接入公司统一 ID 服务。
 * 这里先提供一个轻量 ID 生成器，保证业务代码不用依赖 MySQL 自增。
 * 后续如果公司提供统一雪花 ID 服务，只需要替换本服务实现。
 */
class SnowflakeId
{
    /**
     * 使用固定业务纪元降低最终数字长度。
     *
     * 2026-01-01 00:00:00 UTC 的毫秒时间戳。
     * 生成结果约为：相对毫秒 * 1000000 + 随机 worker 前缀 * 1000 + 请求内序号。
     */
    private const EPOCH_MILLISECONDS = 1767225600000;

    private const WORKER_MULTIPLIER = 1000;

    private const MILLIS_MULTIPLIER = 1000000;

    private int $lastMillisecond = 0;

    private int $sequence = 0;

    private int $workerPrefix;

    public function __construct()
    {
        // 当前阶段没有机器号配置，先用随机 worker 前缀降低并发请求在同一毫秒撞 ID 的概率。
        // 如果未来接入统一 ID 服务或部署固定 worker_id，可以替换这里。
        $this->workerPrefix = random_int(0, 999);
    }

    public function next(): int
    {
        $millisecond = $this->currentMillisecond();

        if ($millisecond === $this->lastMillisecond) {
            // 单个请求同一毫秒内最多生成 1000 个 ID，超过后等待下一毫秒。
            $this->sequence = ($this->sequence + 1) % self::WORKER_MULTIPLIER;

            if ($this->sequence === 0) {
                $millisecond = $this->waitNextMillisecond($millisecond);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastMillisecond = $millisecond;

        return (($millisecond - self::EPOCH_MILLISECONDS) * self::MILLIS_MULTIPLIER)
            + ($this->workerPrefix * self::WORKER_MULTIPLIER)
            + $this->sequence;
    }

    private function currentMillisecond(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function waitNextMillisecond(int $currentMillisecond): int
    {
        do {
            $nextMillisecond = $this->currentMillisecond();
        } while ($nextMillisecond <= $currentMillisecond);

        return $nextMillisecond;
    }
}
