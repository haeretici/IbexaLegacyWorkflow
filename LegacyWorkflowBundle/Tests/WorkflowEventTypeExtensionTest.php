<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\DependencyInjection\Compiler\WorkflowEventTypePass;
use Haeretici\LegacyWorkflowBundle\Workflow\EventType\EzApproveEventType;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\EventType\OnPublishAfterEventType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class WorkflowEventTypeExtensionTest extends TestCase
{
    public function testCompilerPassRegistersTaggedCustomEventType(): void
    {
        $container = new ContainerBuilder();
        $container->register(WorkflowEventTypeRegistry::class);
        $container
            ->register('builtin.approve', EzApproveEventType::class)
            ->addTag(WorkflowEventTypePass::TAG);
        $container
            ->register('custom.on_publish_after', OnPublishAfterEventType::class)
            ->addTag(WorkflowEventTypePass::TAG);

        (new WorkflowEventTypePass())->process($container);

        $calls = $container->getDefinition(WorkflowEventTypeRegistry::class)->getMethodCalls();
        $registeredClasses = array_map(
            static fn (array $call): string => (string) $call[1][0],
            $calls
        );

        $this->assertContains('custom.on_publish_after', $registeredClasses);
        $this->assertContains('builtin.approve', $registeredClasses);
    }
}