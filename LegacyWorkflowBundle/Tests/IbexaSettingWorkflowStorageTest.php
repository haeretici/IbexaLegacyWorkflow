<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\Workflow;
use Haeretici\LegacyWorkflowBundle\Workflow\Model\WorkflowEvent;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\IbexaSettingWorkflowStorage;
use Ibexa\Contracts\Core\Persistence\Setting\Handler as SettingHandler;
use Ibexa\Contracts\Core\Persistence\Setting\Setting;
use Ibexa\Core\Base\Exceptions\NotFoundException;
use PHPUnit\Framework\TestCase;

class IbexaSettingWorkflowStorageTest extends TestCase
{
    /** @var array<string, string> */
    private array $settings = [];

    public function testPersistsWorkflowDataInIbexaSettingTable(): void
    {
        $storage = $this->createStorage();
        $workflow = new Workflow(id: 0, name: 'Standard');
        $storage->upsertWorkflow($workflow);
        $storage->upsertWorkflowEvent(new WorkflowEvent(
            id: 0,
            workflowId: $workflow->id,
            workflowTypeString: EzApproveEventType::TYPE_STRING,
            placement: 1,
        ));
        $storage->assignTriggerToOperation('content_publish', 'after', $workflow->id);

        $reloaded = $this->createStorage();
        $this->assertCount(1, $reloaded->listWorkflows());
        $this->assertSame('Standard', $reloaded->listWorkflows()[0]->name);
        $this->assertNotNull($reloaded->findTrigger('post_publish', 'content', 'publish'));
        $this->assertSame(EzApproveEventType::TYPE_STRING, $reloaded->findWorkflowEvents($workflow->id)[0]->workflowTypeString);
    }

    private function createStorage(): IbexaSettingWorkflowStorage
    {
        return new IbexaSettingWorkflowStorage(
            $this->createSettingHandler(),
            'ibexa_legacy_workflow',
            'workflow_data',
        );
    }

    private function createSettingHandler(): SettingHandler
    {
        $handler = $this->createMock(SettingHandler::class);

        $handler->method('load')->willReturnCallback(function (string $group, string $identifier): Setting {
            $key = $group . ':' . $identifier;
            if (!isset($this->settings[$key])) {
                throw new NotFoundException('Setting', $identifier);
            }

            return new Setting([
                'group' => $group,
                'identifier' => $identifier,
                'serializedValue' => $this->settings[$key],
            ]);
        });

        $handler->method('create')->willReturnCallback(function (string $group, string $identifier, string $serializedValue): Setting {
            $this->settings[$group . ':' . $identifier] = $serializedValue;

            return new Setting([
                'group' => $group,
                'identifier' => $identifier,
                'serializedValue' => $serializedValue,
            ]);
        });

        $handler->method('update')->willReturnCallback(function (string $group, string $identifier, string $serializedValue): Setting {
            $key = $group . ':' . $identifier;
            if (!isset($this->settings[$key])) {
                throw new NotFoundException('Setting', $identifier);
            }

            $this->settings[$key] = $serializedValue;

            return new Setting([
                'group' => $group,
                'identifier' => $identifier,
                'serializedValue' => $serializedValue,
            ]);
        });

        return $handler;
    }
}