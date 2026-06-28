<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullCurrentUserResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\InMemoryWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use PHPUnit\Framework\TestCase;

class TriggerRunnerDisabledWorkflowTest extends TestCase
{
    public function testDisabledWorkflowDoesNotExecute(): void
    {
        $storage = new InMemoryWorkflowStorage();
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(new EzApproveEventType());
        $storage->addWorkflow(new Workflow(id: 9, isEnabled: false));
        $storage->addTrigger(new Trigger(1, 'content', 'publish', 'b', 9, 'pre_publish'));

        $runner = new TriggerRunner(
            $storage,
            new WorkflowProcessRunner($storage, $registry),
            new NullCurrentUserResolver(),
        );

        $result = $runner->runTrigger(
            'pre_publish',
            'content',
            'publish',
            ['object_id' => 1, 'version' => 1],
            ['object_id', 'version']
        );

        $this->assertSame(TriggerStatus::WORKFLOW_CANCELLED, $result['Status']);
    }
}