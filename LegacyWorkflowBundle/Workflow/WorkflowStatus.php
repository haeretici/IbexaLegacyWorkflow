<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow;

final class WorkflowStatus
{
    public const STATUS_NONE = 0;
    public const STATUS_BUSY = 1;
    public const STATUS_DONE = 2;
    public const STATUS_FAILED = 3;
    public const STATUS_DEFERRED_TO_CRON = 4;
    public const STATUS_CANCELLED = 5;
    public const STATUS_FETCH_TEMPLATE = 6;
    public const STATUS_REDIRECT = 7;
    public const STATUS_RESET = 8;
    public const STATUS_WAITING_PARENT = 9;
    public const STATUS_FETCH_TEMPLATE_REPEAT = 10;
}