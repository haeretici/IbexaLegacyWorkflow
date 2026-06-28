<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests\Support;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzFinishUserRegisterEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzMultiplexerEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzWaitUntilDateEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\ContentContextInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullCurrentUserResolver;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\SubWorkflowExecutor;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\TriggerRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\InMemoryWorkflowStorage;

final class WorkflowTestHarness
{
    public readonly InMemoryWorkflowStorage $storage;
    public readonly WorkflowEventTypeRegistry $registry;
    public readonly WorkflowProcessRunner $processRunner;
    public readonly TriggerRunner $triggerRunner;

    public function __construct(ContentContextInterface $contentContext)
    {
        $this->storage = new InMemoryWorkflowStorage();
        $this->registry = new WorkflowEventTypeRegistry();
        $this->processRunner = new WorkflowProcessRunner($this->storage, $this->registry);

        $subWorkflowExecutor = new SubWorkflowExecutor($this->storage);
        $subWorkflowExecutor->setProcessRunner($this->processRunner);

        $this->registry->register(new EzApproveEventType());
        $this->registry->register(new EzWaitUntilDateEventType($contentContext));
        $this->registry->register(new EzMultiplexerEventType($contentContext, $subWorkflowExecutor));
        $this->registry->register(new EzFinishUserRegisterEventType($contentContext));

        $this->triggerRunner = new TriggerRunner(
            $this->storage,
            $this->processRunner,
            new NullCurrentUserResolver(),
        );
    }
}