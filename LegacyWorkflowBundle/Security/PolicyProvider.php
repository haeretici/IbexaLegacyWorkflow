<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Security;

use Ibexa\Bundle\Core\DependencyInjection\Configuration\ConfigBuilderInterface;
use Ibexa\Bundle\Core\DependencyInjection\Security\PolicyProvider\PolicyProviderInterface;

class PolicyProvider implements PolicyProviderInterface
{
    public function addPolicies(ConfigBuilderInterface $configBuilder): void
    {
        $configBuilder->addConfig([
            'workflow' => ['read' => null, 'edit' => null, 'admin' => null],
            'trigger' => ['read' => null, 'edit' => null, 'admin' => null],
        ]);
    }
}