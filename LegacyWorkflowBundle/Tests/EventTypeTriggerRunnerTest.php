<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Tests\Support\WorkflowTestHarness;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzFinishUserRegisterEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzMultiplexerEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzWaitUntilDateEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\ContentContextInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use PHPUnit\Framework\TestCase;

class EventTypeTriggerRunnerTest extends TestCase
{
    public function testWaitUntilDateDefersWhenTimestampIsInFuture(): void
    {
        $future = time() + 3600;
        $contentContext = $this->createMock(ContentContextInterface::class);
        $contentContext->method('getEarliestDateAttributeTimestamp')->willReturn($future);

        $harness = new WorkflowTestHarness($contentContext);
        $this->seedPublishTrigger($harness, workflowId: 11, eventType: EzWaitUntilDateEventType::TYPE_STRING, eventId: 201, dataText1: '42');

        $result = $harness->triggerRunner->runTrigger(
            'pre_publish',
            'content',
            'publish',
            ['object_id' => 10, 'version' => 1, 'user_id' => 1],
            ['object_id', 'version']
        );

        $this->assertSame(TriggerStatus::STATUS_CRON_JOB, $result['Status']);
    }

    public function testFinishUserRegisterCompletesForUserContent(): void
    {
        $contentContext = $this->createMock(ContentContextInterface::class);
        $contentContext->method('isUserContent')->with(99)->willReturn(true);
        $contentContext->expects($this->once())->method('finishUserRegistration')->with(99);

        $harness = new WorkflowTestHarness($contentContext);
        $this->seedPublishTrigger($harness, workflowId: 12, eventType: EzFinishUserRegisterEventType::TYPE_STRING, eventId: 202, triggerName: 'post_publish', connectType: 'a');

        $result = $harness->triggerRunner->runTrigger(
            'post_publish',
            'content',
            'publish',
            ['object_id' => 99, 'version' => 1, 'user_id' => 1],
            ['object_id', 'version']
        );

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $result['Status']);
    }

    public function testMultiplexerRunsChildWorkflowAndReturnsFetchTemplate(): void
    {
        $contentContext = $this->createMock(ContentContextInterface::class);
        $contentContext->method('getSectionId')->willReturn(3);
        $contentContext->method('getContentClassId')->willReturn(7);

        $harness = new WorkflowTestHarness($contentContext);

        $harness->storage->addWorkflow(new Workflow(id: 20));
        $harness->storage->addWorkflowEvent(new WorkflowEvent(
            id: 301,
            workflowId: 20,
            workflowTypeString: EzMultiplexerEventType::TYPE_STRING,
            placement: 1,
            dataInt1: 21,
        ));

        $harness->storage->addWorkflow(new Workflow(id: 21));
        $harness->storage->addWorkflowEvent(new WorkflowEvent(
            id: 302,
            workflowId: 21,
            workflowTypeString: EzApproveEventType::TYPE_STRING,
            placement: 1,
            dataText3: '999',
        ));

        $harness->storage->addTrigger(new Trigger(
            id: 1,
            moduleName: 'content',
            functionName: 'publish',
            connectType: 'b',
            workflowId: 20,
            name: 'pre_publish',
        ));

        $result = $harness->triggerRunner->runTrigger(
            'pre_publish',
            'content',
            'publish',
            ['object_id' => 10, 'version' => 1, 'user_id' => 14],
            ['object_id', 'version']
        );

        $this->assertSame(TriggerStatus::FETCH_TEMPLATE_REPEAT, $result['Status']);
    }

    public function testFindWorkflowAcceptsPublishedVersionZero(): void
    {
        $contentContext = $this->createMock(ContentContextInterface::class);
        $harness = new WorkflowTestHarness($contentContext);

        $harness->storage->addWorkflow(new Workflow(id: 30, version: 1));
        $harness->storage->addTrigger(new Trigger(
            id: 2,
            moduleName: 'content',
            functionName: 'publish',
            connectType: 'a',
            workflowId: 30,
            name: 'post_publish',
        ));

        $result = $harness->triggerRunner->runTrigger(
            'post_publish',
            'content',
            'publish',
            ['object_id' => 1, 'version' => 1],
            ['object_id', 'version']
        );

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $result['Status']);
    }

    private function seedPublishTrigger(
        WorkflowTestHarness $harness,
        int $workflowId,
        string $eventType,
        int $eventId,
        string $dataText1 = '',
        string $triggerName = 'pre_publish',
        string $connectType = 'b',
    ): void {
        $harness->storage->addWorkflow(new Workflow(id: $workflowId));
        $harness->storage->addWorkflowEvent(new WorkflowEvent(
            id: $eventId,
            workflowId: $workflowId,
            workflowTypeString: $eventType,
            placement: 1,
            dataText1: $dataText1,
        ));
        $harness->storage->addTrigger(new Trigger(
            id: 1,
            moduleName: 'content',
            functionName: 'publish',
            connectType: $connectType,
            workflowId: $workflowId,
            name: $triggerName,
        ));
    }
}