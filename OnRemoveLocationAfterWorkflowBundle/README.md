# Haeretici OnRemoveLocationAfter Workflow Bundle

Isolated test extension for [Haeretici LegacyWorkflowBundle](../LegacyWorkflowBundle/README.md). Registers a custom legacy workflow event type for the **after** (`post_removelocation`) hook of `content_removelocation`.

| Property | Value |
|----------|--------|
| Event type string | `event_haeretici_onremovelocationafter` |
| Admin label | On remove location after |
| Allowed trigger | `content` / `removelocation` / `after` only |
| Log file | `var/log/OnRemoveLocationAfterEventType.log` |

Enable this bundle alone to test `content_removelocation` workflow parameters and inspect logs without other operation hooks.

## Installation

1. Register PSR-4 autoload for `Haeretici\OnRemoveLocationAfterWorkflowBundle\` → `OnRemoveLocationAfterWorkflowBundle/` in `composer.json`, then `composer dump-autoload`.
2. Enable in `config/bundles.php` **after** `LegacyWorkflowBundle`:

```php
Haeretici\OnRemoveLocationAfterWorkflowBundle\OnRemoveLocationAfterWorkflowBundle::class => ['all' => true],
```

3. `php bin/console cache:clear`

## Usage

1. Create a workflow and add event **On remove location after** (`event_haeretici_onremovelocationafter`).
2. Assign the workflow to **content_removelocation** → **after** in Settings → Triggers.
3. Remove a location from the content **Locations** tab in admin (Ibexa calls `TrashService::trash()`, not `LocationService::deleteLocation()`), or call `TriggerRunner` in tests with `trigger_name` = `post_removelocation`, `module_name` = `content`, `module_function` = `removelocation`, `connect_type` = `after`.
4. Inspect `var/log/OnRemoveLocationAfterEventType.log` for JSON lines with `triggered_at`, `event_type`, and `parameters`.
