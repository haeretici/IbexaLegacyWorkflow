<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Service\WorkflowIniInspector;
use Haeretici\LegacyWorkflowBundle\Workflow\SupportedOperations;
use Haeretici\OnDeleteAfterWorkflowBundle\Workflow\EventType\OnDeleteAfterEventType;
use Haeretici\OnDeleteAfterWorkflowBundle\Workflow\Service\OnDeleteAfterEventTypeLogger;
use Haeretici\OnHideAfterWorkflowBundle\Workflow\EventType\OnHideAfterEventType;
use Haeretici\OnHideAfterWorkflowBundle\Workflow\Service\OnHideAfterEventTypeLogger;
use Haeretici\OnRemoveLocationAfterWorkflowBundle\Workflow\EventType\OnRemoveLocationAfterEventType;
use Haeretici\OnRemoveLocationAfterWorkflowBundle\Workflow\Service\OnRemoveLocationAfterEventTypeLogger;
use Haeretici\OnShowAfterWorkflowBundle\Workflow\EventType\OnShowAfterEventType;
use Haeretici\OnShowAfterWorkflowBundle\Workflow\Service\OnShowAfterEventTypeLogger;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\EventType\OnPublishAfterEventType;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\Service\OnPublishAfterEventTypeLogger;

final class TestSupport
{
    public const SUPPORTED_OPERATIONS = SupportedOperations::OPERATIONS;

    public const SUPPORTED_EVENT_TYPES = [
        'event_ezapprove',
        'event_ezwaituntildate',
        'event_ezmultiplexer',
        'event_ezfinishuserregister',
    ];

    public static function workflowIniInspector(?string $workflowIniPath = null): WorkflowIniInspector
    {
        return new WorkflowIniInspector(
            self::SUPPORTED_OPERATIONS,
            self::SUPPORTED_EVENT_TYPES,
            $workflowIniPath ?? __DIR__ . '/../Resources/config/workflow.ini',
        );
    }

    public static function onPublishAfterEventType(?string $logsDir = null): OnPublishAfterEventType
    {
        return new OnPublishAfterEventType(
            new OnPublishAfterEventTypeLogger($logsDir ?? sys_get_temp_dir()),
        );
    }

    public static function onDeleteAfterEventType(?string $logsDir = null): OnDeleteAfterEventType
    {
        return new OnDeleteAfterEventType(
            new OnDeleteAfterEventTypeLogger($logsDir ?? sys_get_temp_dir()),
        );
    }

    public static function onHideAfterEventType(?string $logsDir = null): OnHideAfterEventType
    {
        return new OnHideAfterEventType(
            new OnHideAfterEventTypeLogger($logsDir ?? sys_get_temp_dir()),
        );
    }

    public static function onShowAfterEventType(?string $logsDir = null): OnShowAfterEventType
    {
        return new OnShowAfterEventType(
            new OnShowAfterEventTypeLogger($logsDir ?? sys_get_temp_dir()),
        );
    }

    public static function onRemoveLocationAfterEventType(?string $logsDir = null): OnRemoveLocationAfterEventType
    {
        return new OnRemoveLocationAfterEventType(
            new OnRemoveLocationAfterEventTypeLogger($logsDir ?? sys_get_temp_dir()),
        );
    }
}