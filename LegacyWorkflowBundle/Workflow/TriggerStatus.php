<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow;

final class TriggerStatus
{
    public const STATUS_CRON_JOB = 0;
    public const WORKFLOW_DONE = 1;
    public const WORKFLOW_CANCELLED = 2;
    public const NO_CONNECTED_WORKFLOWS = 3;
    public const FETCH_TEMPLATE = 4;
    public const REDIRECT = 5;
    public const WORKFLOW_RESET = 6;
    public const FETCH_TEMPLATE_REPEAT = 7;

    public static function name(int $status): string
    {
        return match ($status) {
            self::STATUS_CRON_JOB => 'STATUS_CRON_JOB',
            self::WORKFLOW_DONE => 'WORKFLOW_DONE',
            self::WORKFLOW_CANCELLED => 'WORKFLOW_CANCELLED',
            self::NO_CONNECTED_WORKFLOWS => 'NO_CONNECTED_WORKFLOWS',
            self::FETCH_TEMPLATE => 'FETCH_TEMPLATE',
            self::REDIRECT => 'REDIRECT',
            self::WORKFLOW_RESET => 'WORKFLOW_RESET',
            self::FETCH_TEMPLATE_REPEAT => 'FETCH_TEMPLATE_REPEAT',
            default => 'UNKNOWN',
        };
    }
}