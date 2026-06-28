<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzFinishUserRegisterEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzMultiplexerEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzWaitUntilDateEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\NullContentContext;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\SubWorkflowExecutor;
use Haeretici\LegacyWorkflowBundle\Workflow\Service\WorkflowProcessRunner;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\InMemoryWorkflowStorage;
use PHPUnit\Framework\TestCase;

class WorkflowEventTypeRegistryTest extends TestCase
{
    public function testSupportedEventTypesFromWorkflowIniAreRegistered(): void
    {
        $registry = new WorkflowEventTypeRegistry();
        $contentContext = new NullContentContext();
        $storage = new InMemoryWorkflowStorage();
        $processRunner = new WorkflowProcessRunner($storage, $registry);
        $subWorkflowExecutor = new SubWorkflowExecutor($storage);
        $subWorkflowExecutor->setProcessRunner($processRunner);

        $registry->register(new EzApproveEventType());
        $registry->register(new EzWaitUntilDateEventType($contentContext));
        $registry->register(new EzMultiplexerEventType($contentContext, $subWorkflowExecutor));
        $registry->register(new EzFinishUserRegisterEventType($contentContext));

        $this->assertSame(
            ['event_ezapprove', 'event_ezwaituntildate', 'event_ezmultiplexer', 'event_ezfinishuserregister'],
            $registry->getRegisteredTypeStrings()
        );
    }

    public function testApproveEventAllowsContentPublishBeforeTrigger(): void
    {
        $type = new EzApproveEventType();

        $this->assertTrue($type->isAllowed('content', 'publish', 'before'));
        $this->assertFalse($type->isAllowed('content', 'publish', 'after'));
    }
}