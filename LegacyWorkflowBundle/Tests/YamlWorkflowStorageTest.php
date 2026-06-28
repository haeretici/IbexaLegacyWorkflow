<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Trigger;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\YamlWorkflowStorage;
use PHPUnit\Framework\TestCase;

class YamlWorkflowStorageTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/legacy-workflow-' . uniqid('', true) . '.yaml';
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    public function testPersistsWorkflowTriggerAndEventDefinitions(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $workflow = new Workflow(id: 0, name: 'Approval');
        $storage->upsertWorkflow($workflow);
        $storage->upsertWorkflowEvent(new WorkflowEvent(
            id: 0,
            workflowId: $workflow->id,
            workflowTypeString: EzApproveEventType::TYPE_STRING,
            placement: 1,
            dataText3: '999',
        ));
        $storage->assignTriggerToOperation('content_publish', 'before', $workflow->id);

        $reloaded = new YamlWorkflowStorage($this->storagePath);

        $this->assertCount(1, $reloaded->listWorkflows());
        $this->assertSame('Approval', $reloaded->listWorkflows()[0]->name);
        $this->assertNotNull($reloaded->findTrigger('pre_publish', 'content', 'publish'));
        $this->assertSame(EzApproveEventType::TYPE_STRING, $reloaded->findWorkflowEvents($workflow->id)[0]->workflowTypeString);
    }

    public function testRemovingTriggerAssignmentClearsPersistedTrigger(): void
    {
        $storage = new YamlWorkflowStorage($this->storagePath);
        $workflow = new Workflow(id: 0, name: 'Empty');
        $storage->upsertWorkflow($workflow);
        $storage->assignTriggerToOperation('content_publish', 'after', $workflow->id);
        $storage->assignTriggerToOperation('content_publish', 'after', 0);

        $reloaded = new YamlWorkflowStorage($this->storagePath);

        $this->assertNull($reloaded->findTrigger('post_publish', 'content', 'publish'));
    }
}