<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\DependencyInjection\Compiler;

use Haeretici\LegacyWorkflowBundle\Workflow\EventType\WorkflowEventTypeInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class WorkflowEventTypePass implements CompilerPassInterface
{
    public const TAG = 'haeretici.legacy_workflow.event_type';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(WorkflowEventTypeRegistry::class)) {
            return;
        }

        $registry = $container->findDefinition(WorkflowEventTypeRegistry::class);
        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $registry->addMethodCall('register', [new Reference($id)]);
        }
    }
}