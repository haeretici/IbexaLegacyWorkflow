<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullCurrentUserResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\InMemoryWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use PHPUnit\Framework\TestCase;

class TriggerRunnerTest extends TestCase
{
    private InMemoryWorkflowStorage $storage;
    private TriggerRunner $triggerRunner;

    protected function setUp(): void
    {
        $this->storage = new InMemoryWorkflowStorage();
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(new EzApproveEventType());
        $this->triggerRunner = new TriggerRunner(
            $this->storage,
            new WorkflowProcessRunner($this->storage, $registry),
            new NullCurrentUserResolver(),
        );
    }

    public function testRunTriggerWithoutConnectedWorkflowReturnsNoConnectedWorkflows(): void
    {
        $result = $this->triggerRunner->runTrigger('pre_publish', 'content', 'publish', ['object_id' => 10, 'version' => 1], ['object_id', 'version']);
        $this->assertSame(TriggerStatus::NO_CONNECTED_WORKFLOWS, $result['Status']);
    }

    public function testRunTriggerWithEmptyWorkflowReturnsWorkflowDone(): void
    {
        $this->storage->addWorkflow(new Workflow(id: 5));
        $this->storage->addTrigger(new Trigger(1, 'content', 'publish', 'b', 5, 'pre_publish'));
        $result = $this->triggerRunner->runTrigger('pre_publish', 'content', 'publish', ['object_id' => 10, 'version' => 1], ['object_id', 'version']);
        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $result['Status']);
    }

    public function testRunTriggerWithApproveEventReturnsFetchTemplateWhenApprovalRequired(): void
    {
        $this->storage->addWorkflow(new Workflow(id: 7));
        $this->storage->addTrigger(new Trigger(2, 'content', 'publish', 'b', 7, 'pre_publish'));
        $this->storage->addWorkflowEvent(new WorkflowEvent(100, 7, EzApproveEventType::TYPE_STRING, placement: 1, dataText3: '999'));
        $result = $this->triggerRunner->runTrigger('pre_publish', 'content', 'publish', ['object_id' => 10, 'version' => 1, 'user_id' => 14], ['object_id', 'version']);
        $this->assertSame(TriggerStatus::FETCH_TEMPLATE_REPEAT, $result['Status']);
    }
}