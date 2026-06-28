<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Storage;

use Ibexa\Contracts\Core\Persistence\Setting\Handler as SettingHandler;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Symfony\Component\Yaml\Yaml;

final class IbexaSettingWorkflowStorage extends AbstractPersistedWorkflowStorage
{
    public function __construct(
        private readonly SettingHandler $settingHandler,
        private readonly string $settingGroup,
        private readonly string $settingIdentifier,
        private readonly ?string $yamlMigrationPath = null,
    ) {
        parent::__construct();
        $this->migrateFromYamlIfNeeded();
    }

    /** @return array<string, mixed>|null */
    protected function loadFromBackend(): ?array
    {
        try {
            $setting = $this->settingHandler->load($this->settingGroup, $this->settingIdentifier);
        } catch (NotFoundException) {
            return null;
        }

        $data = json_decode($setting->serializedValue, true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    protected function persistToBackend(array $data): void
    {
        $serializedValue = json_encode($data, JSON_THROW_ON_ERROR);

        try {
            $this->settingHandler->update($this->settingGroup, $this->settingIdentifier, $serializedValue);
        } catch (NotFoundException) {
            $this->settingHandler->create($this->settingGroup, $this->settingIdentifier, $serializedValue);
        }
    }

    private function migrateFromYamlIfNeeded(): void
    {
        if ($this->workflows !== [] || $this->triggers !== [] || $this->events !== [] || $this->processes !== []) {
            return;
        }

        if ($this->yamlMigrationPath === null || !is_file($this->yamlMigrationPath)) {
            return;
        }

        $data = Yaml::parseFile($this->yamlMigrationPath);
        if (!is_array($data)) {
            return;
        }

        $this->importData($data);
        $this->persistToBackend($this->exportData());
    }
}