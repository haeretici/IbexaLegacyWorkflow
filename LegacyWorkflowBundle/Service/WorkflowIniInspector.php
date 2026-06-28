<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Service;

final class WorkflowIniInspector
{
    public function __construct(
        /** @var string[] */
        private readonly array $supportedOperations,
        /** @var string[] */
        private readonly array $supportedEventTypes,
        private readonly ?string $workflowIniPath = null,
    ) {
    }

    /** @return string[] */
    public function getAvailableOperations(): array
    {
        return array_values(array_unique(array_filter(
            $this->supportedOperations,
            static fn (string $operation): bool => $operation !== ''
        )));
    }

    /** @return string[] */
    public function getAvailableEventTypes(): array
    {
        return array_values(array_unique(array_filter(
            $this->supportedEventTypes,
            static fn (string $eventType): bool => $eventType !== ''
        )));
    }

    /** @return string[] */
    public function getLegacyIniOperations(): array
    {
        return $this->readIniSection('OperationSettings', 'AvailableOperationList');
    }

    /** @return string[] */
    public function getLegacyIniEventTypes(): array
    {
        return $this->readIniSection('EventSettings', 'AvailableEventTypes');
    }

    /** @return string[] */
    private function readIniSection(string $section, string $key): array
    {
        $path = $this->resolveIniPath();
        if ($path === null || !is_readable($path)) {
            return [];
        }

        $values = [];
        $inSection = false;
        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                continue;
            }

            if (preg_match('/^\[(.+)]$/', $trimmed, $matches)) {
                $inSection = $matches[1] === $section;
                continue;
            }

            if (!$inSection || !str_starts_with($trimmed, $key)) {
                continue;
            }

            if (preg_match('/^' . preg_quote($key, '/') . '\[\]=(.*)$/', $trimmed, $matches)) {
                $values[] = trim($matches[1]);
            }
        }

        return $values;
    }

    private function resolveIniPath(): ?string
    {
        if ($this->workflowIniPath !== null && is_readable($this->workflowIniPath)) {
            return $this->workflowIniPath;
        }

        $candidates = [
            __DIR__ . '/../Resources/config/workflow.ini',
            __DIR__ . '/../../legacy/settings/workflow.ini',
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}