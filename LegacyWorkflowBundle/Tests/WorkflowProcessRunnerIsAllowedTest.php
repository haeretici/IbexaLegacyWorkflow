<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowProcess;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\InMemoryWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\WorkflowStatus;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\EventType\OnPublishAfterEventType;
use PHPUnit\Framework\TestCase;

class WorkflowProcessRunnerIsAllowedTest extends TestCase
{
    public function testBeforeOnlyEventIsSkippedOnAfterTrigger(): void
    {
        $storage = new InMemoryWorkflowStorage();
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(new EzApproveEventType());
        $storage->addWorkflow(new Workflow(id: 1));
        $storage->addWorkflowEvent(new WorkflowEvent(10, 1, EzApproveEventType::TYPE_STRING, placement: 1, dataText3: '999'));

        $process = new WorkflowProcess();
        $process->workflowId = 1;
        $process->parameters = [
            'module_name' => 'content',
            'module_function' => 'publish',
            'connect_type' => 'after',
            'trigger_name' => 'post_publish',
            'object_id' => 5,
            'version' => 1,
            'user_id' => 3,
        ];

        $runner = new WorkflowProcessRunner($storage, $registry);
        $status = $runner->run($process, $storage->findWorkflow(1));

        $this->assertSame(WorkflowStatus::STATUS_DONE, $status);
    }

    public function testAfterOnlyEventRunsOnAfterTrigger(): void
    {
        $storage = new InMemoryWorkflowStorage();
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(TestSupport::onPublishAfterEventType());
        $storage->addWorkflow(new Workflow(id: 2));
        $storage->addWorkflowEvent(new WorkflowEvent(11, 2, OnPublishAfterEventType::TYPE_STRING, placement: 1));

        $process = new WorkflowProcess();
        $process->workflowId = 2;
        $process->parameters = [
            'module_name' => 'content',
            'module_function' => 'publish',
            'connect_type' => 'after',
            'trigger_name' => 'post_publish',
            'object_id' => 8,
            'version' => 2,
        ];

        $runner = new WorkflowProcessRunner($storage, $registry);
        $status = $runner->run($process, $storage->findWorkflow(2));

        $this->assertSame(WorkflowStatus::STATUS_DONE, $status);
    }
}