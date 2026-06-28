<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullCurrentUserResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\YamlWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\TriggerStatus;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\EventType\OnPublishAfterEventType;
use PHPUnit\Framework\TestCase;

class TriggerRunnerProcessPersistenceTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/legacy-process-persist-' . uniqid('', true) . '.yaml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    public function testCompletedProcessIsRemovedFromYamlStorage(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $registry = new WorkflowEventTypeRegistry();
        $registry->register(TestSupport::onPublishAfterEventType());

        $workflow = new Workflow(id: 0, name: 'Standard');
        $storage->upsertWorkflow($workflow);
        $storage->upsertWorkflowEvent(new WorkflowEvent(
            id: 0,
            workflowId: $workflow->id,
            workflowTypeString: OnPublishAfterEventType::TYPE_STRING,
            placement: 1,
        ));
        $storage->assignTriggerToOperation('content_publish', 'after', $workflow->id);

        $triggerRunner = new TriggerRunner(
            $storage,
            new WorkflowProcessRunner($storage, $registry),
            new NullCurrentUserResolver(),
        );

        $result = $triggerRunner->runTrigger(
            'post_publish',
            'content',
            'publish',
            ['object_id' => 42, 'version' => 1, 'user_id' => 3],
            ['object_id', 'version']
        );

        $this->assertSame(TriggerStatus::WORKFLOW_DONE, $result['Status']);

        $reloaded = new YamlWorkflowStorage($this->storagePath);
        $this->assertSame([], $reloaded->listProcesses());
    }
}