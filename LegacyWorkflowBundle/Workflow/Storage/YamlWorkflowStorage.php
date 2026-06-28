<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Storage;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class YamlWorkflowStorage extends AbstractPersistedWorkflowStorage
{
    public function __construct(
        private readonly string $storagePath,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed>|null */
    protected function loadFromBackend(): ?array
    {
        if (!is_file($this->storagePath)) {
            return null;
        }

        $data = Yaml::parseFile($this->storagePath);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    protected function persistToBackend(array $data): void
    {
        $directory = \dirname($this->storagePath);
        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }

        $this->filesystem->dumpFile($this->storagePath, Yaml::dump($data, 4, 2));
    }
}