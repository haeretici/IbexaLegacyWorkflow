<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Tests;

use Haeretici\LegacyWorkflowBundle\DependencyInjection\Compiler\WorkflowEventTypePass;
use Haeretici\LegacyWorkflowBundle\DependencyInjection\LegacyWorkflowExtension;
use Haeretici\LegacyWorkflowBundle\EventSubscriber\ContentOperationsWorkflowSubscriber;
use Haeretici\LegacyWorkflowBundle\EventSubscriber\PublishWorkflowSubscriber;
use Haeretici\LegacyWorkflowBundle\LegacyWorkflowBundle;
use Haeretici\LegacyWorkflowBundle\Workflow\Registry\WorkflowEventTypeRegistry;
use Haeretici\OnPublishAfterWorkflowBundle\DependencyInjection\OnPublishAfterWorkflowExtension;
use Haeretici\OnPublishAfterWorkflowBundle\OnPublishAfterWorkflowBundle;
use Haeretici\OnPublishAfterWorkflowBundle\Workflow\EventType\OnPublishAfterEventType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BundleContainerBootTest extends TestCase
{
    public function testExtensionLoadsSubscriberRegistryAndTaggedCustomEventType(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.logs_dir', sys_get_temp_dir());

        (new LegacyWorkflowBundle())->build($container);
        (new LegacyWorkflowExtension())->load([[
            'enabled' => true,
            'storage_path' => sys_get_temp_dir() . '/legacy-workflow-boot.yaml',
            'workflow_ini_path' => __DIR__ . '/../Resources/config/workflow.ini',
        ]], $container);

        (new OnPublishAfterWorkflowBundle())->build($container);
        (new OnPublishAfterWorkflowExtension())->load([], $container);

        $this->assertTrue($container->hasDefinition(WorkflowEventTypeRegistry::class));
        $this->assertTrue($container->hasDefinition(PublishWorkflowSubscriber::class));
        $this->assertTrue($container->getDefinition(PublishWorkflowSubscriber::class)->hasTag('kernel.event_subscriber'));
        $this->assertTrue($container->hasDefinition(ContentOperationsWorkflowSubscriber::class));
        $this->assertTrue($container->getDefinition(ContentOperationsWorkflowSubscriber::class)->hasTag('kernel.event_subscriber'));
        $this->assertTrue($container->hasDefinition(OnPublishAfterEventType::class));
        $this->assertTrue($container->getDefinition(OnPublishAfterEventType::class)->hasTag(WorkflowEventTypePass::TAG));

        (new WorkflowEventTypePass())->process($container);

        $registeredIds = [];
        foreach ($container->getDefinition(WorkflowEventTypeRegistry::class)->getMethodCalls() as $call) {
            if ($call[0] === 'register') {
                $registeredIds[] = (string) $call[1][0];
            }
        }

        $this->assertContains(OnPublishAfterEventType::class, $registeredIds);
    }
}