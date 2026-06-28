<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle;

use Haeretici\LegacyWorkflowBundle\DependencyInjection\Compiler\WorkflowEventTypePass;
use Haeretici\LegacyWorkflowBundle\DependencyInjection\LegacyWorkflowExtension;
use Haeretici\LegacyWorkflowBundle\Security\PolicyProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class LegacyWorkflowBundle extends Bundle
{
    /**
     * Symfony expects the extension alias to be "legacy_workflow" (underscored bundle name).
     * We intentionally use "ibexa_legacy_workflow" in config/packages — override this method
     * to register that alias without triggering the naming-convention check.
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new LegacyWorkflowExtension();
        }

        return $this->extension ?: null;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new WorkflowEventTypePass());

        if ($container->hasExtension('ibexa')) {
            $container->getExtension('ibexa')->addPolicyProvider(new PolicyProvider());
        }
    }
}