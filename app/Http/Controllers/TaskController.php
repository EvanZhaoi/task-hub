<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(): Response
    {
        $tasks = Task::query()
            ->select([
                'id',
                'title',
                'description',
                'budget',
                'final_amount',
                'expected_delivery',
                'final_delivery',
                'bidding_deadline',
                'status',
                'assignment_type',
                'complexity',
                'created_by',
                'created_by_snapshot',
                'payment_account_snapshot',
                'created_at',
            ])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function (Task $task): array {
                $createdBySnapshot = $task->created_by_snapshot ?? [];
                $paymentAccountSnapshot = $task->payment_account_snapshot ?? [];

                return [
                    'id' => (string) $task->id,
                    'title' => $task->title,
                    'description' => Str::limit(strip_tags((string) $task->description), 120),
                    'amountLabel' => $this->amountLabel($task),
                    'expectedDelivery' => $task->expected_delivery?->toDateString(),
                    'finalDelivery' => $task->final_delivery?->toDateString(),
                    'biddingDeadline' => $task->bidding_deadline?->format('Y-m-d H:i'),
                    'status' => $task->status,
                    'assignmentType' => $task->assignment_type,
                    'complexity' => $task->complexity,
                    'createdBy' => $task->created_by,
                    'createdByName' => $createdBySnapshot['displayName'] ?? $task->created_by,
                    'departmentName' => $createdBySnapshot['departmentName'] ?? null,
                    'paymentAccountName' => $paymentAccountSnapshot['accountName'] ?? null,
                ];
            })
            ->values();

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
        ]);
    }

    private function amountLabel(Task $task): string
    {
        $amount = $task->final_amount ?? $task->budget;

        return '¥'.number_format((float) $amount, 2);
    }
}
