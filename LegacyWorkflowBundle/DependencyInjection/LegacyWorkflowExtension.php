<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\DependencyInjection;

use Haeretici\LegacyWorkflowBundle\Workflow\Storage\IbexaSettingWorkflowStorage;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\WorkflowAdminStorageInterface;
use Haeretici\LegacyWorkflowBundle\Workflow\Storage\YamlWorkflowStorage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

class LegacyWorkflowExtension extends Extension implements PrependExtensionInterface
{
    public function getAlias(): string
    {
        return 'ibexa_legacy_workflow';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ibexa_legacy_workflow.enabled', $config['enabled']);
        $container->setParameter('ibexa_legacy_workflow.storage_backend', $config['storage_backend']);
        $container->setParameter('ibexa_legacy_workflow.setting_group', $config['setting_group']);
        $container->setParameter('ibexa_legacy_workflow.setting_identifier', $config['setting_identifier']);
        $container->setParameter('ibexa_legacy_workflow.storage_path', $config['storage_path']);
        $container->setParameter('ibexa_legacy_workflow.workflow_ini_path', $config['workflow_ini_path']);
        $container->setParameter('ibexa_legacy_workflow.available_operations', $config['available_operations']);
        $container->setParameter('ibexa_legacy_workflow.available_event_types', $config['available_event_types']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $storageClass = $config['storage_backend'] === 'yaml'
            ? YamlWorkflowStorage::class
            : IbexaSettingWorkflowStorage::class;

        $container->setAlias(WorkflowAdminStorageInterface::class, $storageClass);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $configFile = __DIR__ . '/../Resources/config/ibexa.yaml';
        if (!is_readable($configFile)) {
            return;
        }

        $config = Yaml::parseFile($configFile);
        if (!is_array($config) || empty($config['ibexa'])) {
            return;
        }

        $container->prependExtensionConfig('ibexa', $config['ibexa']);
        $container->addResource(new FileResource($configFile));
    }
}