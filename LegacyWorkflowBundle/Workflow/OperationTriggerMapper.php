<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow;

/**
 * Maps legacy operation strings (e.g. content_hide) to module, function, and trigger names.
 */
final class OperationTriggerMapper
{
    /** @return array{module: string, function: string} */
    public static function parseOperation(string $operation): array
    {
        $parts = explode('_', $operation);
        $index = 0;
        if (count($parts) >= 3 && in_array($parts[0], ['before', 'after'], true)) {
            ++$index;
        }

        return [
            'module' => $parts[$index] ?? 'content',
            'function' => $parts[$index + 1] ?? 'publish',
        ];
    }

    public static function triggerName(string $function, string $connectType): string
    {
        return ($connectType === 'after' ? 'post_' : 'pre_') . $function;
    }

    public static function connectLetter(string $connectType): string
    {
        return $connectType === 'after' ? 'a' : 'b';
    }

    /** @return array{0: string, 1: string, 2: string} */
    public static function map(string $operation, string $connectType): array
    {
        $parsed = self::parseOperation($operation);

        return [
            $parsed['module'],
            $parsed['function'],
            self::triggerName($parsed['function'], $connectType),
        ];
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    public static function mapWithConnectLetter(string $operation, string $connectType): array
    {
        [$module, $function, $triggerName] = self::map($operation, $connectType);

        return [$module, $function, $triggerName, self::connectLetter($connectType)];
    }
}