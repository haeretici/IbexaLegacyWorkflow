<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow\Service;

final class ProcessKeyBuilder
{
    /** @param array<string, mixed> $parameters @param string[]|null $keys */
    public static function createKey(array $parameters, ?array $keys = null): string
    {
        $string = '';
        $parameterKeys = $keys ?? array_keys($parameters);

        foreach ($parameterKeys as $key) {
            $value = $parameters[$key] ?? '';
            if (is_array($value)) {
                $value = serialize($value);
            }
            $string .= $key . $value;
        }

        return md5($string);
    }
}