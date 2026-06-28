<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;

final class TriggerResultEvaluator
{
    public static function haltingStatuses(): array
    {
        return [
            TriggerStatus::STATUS_CRON_JOB,
            TriggerStatus::FETCH_TEMPLATE,
            TriggerStatus::FETCH_TEMPLATE_REPEAT,
            TriggerStatus::REDIRECT,
            TriggerStatus::WORKFLOW_RESET,
        ];
    }

    public static function shouldHaltOperation(int $status): bool
    {
        return in_array($status, self::haltingStatuses(), true);
    }

    public static function shouldCancelOperation(int $status): bool
    {
        return $status === TriggerStatus::WORKFLOW_CANCELLED;
    }
}