# Haeretici OnRemoveTranslationAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_removetranslation`) hook of `content_removetranslation`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onremovetranslationafter` |
| Admin label | On remove translation after |
| Allowed trigger | `content` / `removetranslation` / `after` only |
| Log file | `var/log/OnRemoveTranslationAfterEventType.log` |

Enable this bundle alone to test `content_removetranslation` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnRemoveTranslationAfterWorkflowBundle\` → `OnRemoveTranslationAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnRemoveTranslationAfterWorkflowBundle\OnRemoveTranslationAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On remove translation after** (`event_haeretici_onremovetranslationafter`).
2. Assign the workflow to **content_removetranslation** → **after** in Settings → Triggers.
3. Invoke the operation (or call `TriggerRunner` in tests with `trigger_name` = `post_removetranslation`, `module_name` = `content`, `module_function` = `removetranslation`, `connect_type` = `after`).
4. Inspect `var/log/OnRemoveTranslationAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
