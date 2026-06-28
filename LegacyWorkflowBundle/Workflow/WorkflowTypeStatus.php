<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow;

final class WorkflowTypeStatus
{
    public const STATUS_NONE = 0;
    public const STATUS_ACCEPTED = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_DEFERRED_TO_CRON = 3;
    public const STATUS_DEFERRED_TO_CRON_REPEAT = 4;
    public const STATUS_RUN_SUB_EVENT = 5;
    public const STATUS_WORKFLOW_CANCELLED = 6;
    public const STATUS_FETCH_TEMPLATE = 7;
    public const STATUS_FETCH_TEMPLATE_REPEAT = 8;
    public const STATUS_WORKFLOW_DONE = 9;
    public const STATUS_REDIRECT = 10;
    public const STATUS_REDIRECT_REPEAT = 11;
    public const STATUS_WORKFLOW_RESET = 12;
}