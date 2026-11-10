<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\ExpireOpenTasksService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 分批扫描并处理已到招标截止时间的 OPEN 任务。
 *
 * 命令不为每个任务创建独立 Cron，而是由 Laravel Scheduler 每分钟触发一次。
 * 单轮最多运行45秒，剩余候选任务由下一分钟继续处理。
 */
class ExpireOpenTasksCommand extends Command
{
    private const BATCH_SIZE = 100;

    private const MAX_RUNTIME_SECONDS = 45;

    /** @var string */
    protected $signature = 'taskhub:expire-open-tasks';

    /** @var string */
    protected $description = '处理已截止的公开招标任务，并发送待选标或流标通知';

    /**
     * 执行分批扫描。
     *
     * 查询使用 bidding_deadline + id 作为游标，不使用 OFFSET。任务处理后可能从 OPEN 集合中移除，
     * OFFSET 分页会因此跳过数据，而游标分页可以稳定地向后扫描。
     */
    public function handle(ExpireOpenTasksService $expireOpenTasks): int
    {
        $startedAt = microtime(true);
        $cutoff = now();
        $lastDeadline = null;
        $lastId = null;
        $statistics = [
            'scanned' => 0,
            'selectionRequired' => 0,
            'failedNoBid' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        while ($this->withinRuntime($startedAt)) {
            $tasks = Task::query()
                ->select(['id', 'bidding_deadline'])
                ->where('assignment_type', 'BIDDING')
                ->where('status', 'OPEN')
                ->whereNotNull('bidding_deadline')
                ->where('bidding_deadline', '<=', $cutoff)
                ->when($lastDeadline !== null && $lastId !== null, function (Builder $query) use ($lastDeadline, $lastId): void {
                    $query->where(function (Builder $query) use ($lastDeadline, $lastId): void {
                        $query
                            ->where('bidding_deadline', '>', $lastDeadline)
                            ->orWhere(function (Builder $query) use ($lastDeadline, $lastId): void {
                                $query
                                    ->where('bidding_deadline', $lastDeadline)
                                    ->where('id', '>', $lastId);
                            });
                    });
                })
                ->orderBy('bidding_deadline')
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($tasks->isEmpty()) {
                break;
            }

            foreach ($tasks as $task) {
                if (! $this->withinRuntime($startedAt)) {
                    break 2;
                }

                $statistics['scanned']++;

                try {
                    $outcome = $expireOpenTasks->execute($task->id);
                    $this->recordOutcome($statistics, $outcome);
                } catch (Throwable $exception) {
                    $statistics['errors']++;

                    // 单个任务异常不能阻断整轮扫描；下一分钟会再次尝试仍符合条件的任务。
                    Log::error('招标截止任务处理失败。', [
                        'task_id' => (string) $task->id,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
                }

                $lastDeadline = $task->bidding_deadline;
                $lastId = $task->id;
            }

            if ($tasks->count() < self::BATCH_SIZE) {
                break;
            }
        }

        $this->info(sprintf(
            '截止任务扫描完成：扫描 %d，待选标 %d，自动流标 %d，跳过 %d，失败 %d。',
            $statistics['scanned'],
            $statistics['selectionRequired'],
            $statistics['failedNoBid'],
            $statistics['skipped'],
            $statistics['errors'],
        ));

        return self::SUCCESS;
    }

    /**
     * 判断当前命令是否仍在允许的运行时间内。
     */
    private function withinRuntime(float $startedAt): bool
    {
        return microtime(true) - $startedAt < self::MAX_RUNTIME_SECONDS;
    }

    /**
     * 将单任务结果累加到命令统计中。
     *
     * 已处理过的待选标事件与状态变化后的任务都归入 skipped，不重复发送邮件。
     *
     * @param  array<string, int>  $statistics
     */
    private function recordOutcome(array &$statistics, string $outcome): void
    {
        match ($outcome) {
            ExpireOpenTasksService::OUTCOME_SELECTION_REQUIRED => $statistics['selectionRequired']++,
            ExpireOpenTasksService::OUTCOME_FAILED_NO_BID => $statistics['failedNoBid']++,
            default => $statistics['skipped']++,
        };
    }
}
