<?php

declare(strict_types=1);

namespace Haeretici\LegacyWorkflowBundle\Workflow;

/**
 * Legacy operations exposed in the admin trigger matrix (ibexa_legacy_workflow.available_operations).
 *
 * @see SUPPORTED.md
 */
final class SupportedOperations
{
    /** @var string[] */
    public const OPERATIONS = [
        'content_publish',
        'content_hide',
        'content_show',
        'content_delete',
        'content_move',
        'content_addlocation',
        'content_removelocation',
        'content_swap',
        'content_updatepriority',
        'content_removetranslation',
        'content_updateobjectstate',
        'content_updatesection',
    ];
}