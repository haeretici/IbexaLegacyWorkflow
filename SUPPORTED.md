# Supported legacy workflow scope on Ibexa DXP 4.x

This document maps legacy eZ Publish `workflow.ini` operations and event types to what **Haeretici LegacyWorkflowBundle** can run today using **public Ibexa Repository events only** (no kernel hacks, no legacy `ezworkflow*` tables).

## Currently implemented

### Operations (triggers)

Trigger names follow `pre_{function}` / `post_{function}` where `{function}` is the legacy operation function (e.g. `content_hide` → `pre_hide` / `post_hide`). Mapped via `OperationTriggerMapper`.

| Legacy operation | Before trigger | After trigger | Ibexa Repository event(s) | Subscriber |
|------------------|----------------|---------------|---------------------------|------------|
| `content_publish` | `pre_publish` | `post_publish` | `BeforePublishVersionEvent` / `PublishVersionEvent` | `PublishWorkflowSubscriber` |
| `content_hide` | `pre_hide` | `post_hide` | `BeforeHideLocationEvent` / `HideLocationEvent` | `ContentOperationsWorkflowSubscriber` |
| `content_show` | `pre_show` | `post_show` | `BeforeUnhideLocationEvent` / `UnhideLocationEvent` | `ContentOperationsWorkflowSubscriber` |
| `content_delete` | `pre_delete` | `post_delete` | `BeforeTrashEvent` / `TrashEvent` when trashing the main location (admin move to trash); `BeforeDeleteTrashItemEvent` / `DeleteTrashItemEvent` (permanent delete); `BeforeDeleteContentEvent` / `DeleteContentEvent` (hard delete API) | `ContentOperationsWorkflowSubscriber` |
| `content_move` | `pre_move` | `post_move` | `BeforeMoveSubtreeEvent` / `MoveSubtreeEvent` | `ContentOperationsWorkflowSubscriber` |
| `content_addlocation` | `pre_addlocation` | `post_addlocation` | `BeforeCreateLocationEvent` / `CreateLocationEvent` | `ContentOperationsWorkflowSubscriber` |
| `content_removelocation` | `pre_removelocation` | `post_removelocation` | `BeforeTrashEvent` / `TrashEvent` when trashing a non-main location; `BeforeDeleteLocationEvent` / `DeleteLocationEvent` (hard delete API) | `ContentOperationsWorkflowSubscriber` |
| `content_swap` | `pre_swap` | `post_swap` | `BeforeSwapLocationEvent` / `SwapLocationEvent` | `ContentOperationsWorkflowSubscriber` |
| `content_updatepriority` | `pre_updatepriority` | `post_updatepriority` | `BeforeUpdateLocationEvent` / `UpdateLocationEvent` (priority changes only) | `ContentOperationsWorkflowSubscriber` |
| `content_removetranslation` | `pre_removetranslation` | `post_removetranslation` | `BeforeDeleteTranslationEvent` / `DeleteTranslationEvent` | `ContentOperationsWorkflowSubscriber` |
| `content_updateobjectstate` | `pre_updateobjectstate` | `post_updateobjectstate` | `BeforeSetContentStateEvent` / `SetContentStateEvent` | `ContentOperationsWorkflowSubscriber` |
| `content_updatesection` | `pre_updatesection` | `post_updatesection` | `BeforeAssignSectionEvent` / `AssignSectionEvent` (+ subtree: `BeforeAssignSectionToSubtreeEvent` / `AssignSectionToSubtreeEvent`) | `ContentOperationsWorkflowSubscriber` |

Admin UI: **Settings → Triggers** shows only operations listed in `ibexa_legacy_workflow.available_operations`. The bundle default lists all mappable content operations (see `SupportedOperations::OPERATIONS`); all twelve have event subscribers wired.

`content_hide` and `content_show` use location hide/reveal events (admin tree parity; legacy `node_id`). Unlike legacy eZ Publish (one `content_hide` toggle for both directions), Ibexa exposes separate repository events — `content_show` is an explicit operation with its own triggers. Subscribers pass stable parameter keys: `object_id`, `node_id`, `version`, `user_id`, `operation`, plus operation-specific keys where legacy did (`node_id_list`, `selected_section_id`, etc.).

### Built-in event types

| Legacy type | Purpose | Allowed on `content_publish` |
|-------------|---------|------------------------------|
| `event_ezapprove` | Approval gate (template fetch) | `before` |
| `event_ezwaituntildate` | Defer until date attribute | `before`, `after` |
| `event_ezmultiplexer` | Run child workflows | `before`, `after` |
| `event_ezfinishuserregister` | Activate user after user content publish | `after` |

### Extension event types

Custom bundles may register handlers tagged `haeretici.legacy_workflow.event_type`.

- Identifiers should use the `event_haeretici_*` prefix.
- They appear in the admin UI even when absent from legacy `workflow.ini`.
- One isolated test bundle per supported operation (`On*AfterWorkflowBundle/`), each registering `event_haeretici_on{function}after` on `content` / `{function}` / `after` and logging to `var/log/On{Function}AfterEventType.log`. Enable a single bundle to test that operation without cross-talk. See `AGENTS.md` for the full list.

## Not supported (removed from runtime scope)

### Shop operations

Legacy `workflow.ini` lists shop triggers; Ibexa DXP 4.x has no equivalent checkout pipeline.

| Legacy operation | Reason |
|------------------|--------|
| `before_shop_confirmorder` | Legacy shop kernel |
| `shop_checkout` | Legacy shop kernel |
| `shop_addtobasket` | Legacy shop kernel |
| `shop_updatebasket` | Legacy shop kernel |

### Shop-related event types

| Legacy type | Reason |
|-------------|--------|
| `event_ezsimpleshipping` | Requires shop checkout |
| `event_ezpaymentgateway` | Requires shop payment |

### Other legacy operations without a Repository hook

| Legacy operation | Reason |
|------------------|--------|
| `content_read` | Frontend read; no `ContentService` publish-style hook |
| `content_sort` | No single legacy-compatible Repository event |
| `content_updatemainassignment` | Covered indirectly by location/metadata updates |
| `content_updateinitiallanguage` | Metadata update subset |
| `content_updatealwaysavailable` | Metadata update subset |
| `content_createnodefeed` / `content_removenodefeed` | Legacy feed feature |
| `user_activation` | Legacy user module operation; not `UserService` create |
| `user_password` / `user_forgotpassword` / `user_preferences` / `user_setsettings` | User UI flows, not content triggers |

## Configuration source of truth

Supported lists are **not** taken from the full legacy `workflow.ini` at runtime.

```yaml
# config/packages/ibexa_legacy_workflow.yaml
ibexa_legacy_workflow:
    storage_backend: ibexa_setting
    setting_group: ibexa_legacy_workflow
    setting_identifier: workflow_data
    # Defaults to SupportedOperations::OPERATIONS when omitted.
    available_event_types:
        - event_ezapprove
        - event_ezwaituntildate
        - event_ezmultiplexer
        - event_ezfinishuserregister
```

Persistence uses one row in `ibexa_setting` with a JSON document (workflows, events, triggers, processes, ID counters). Set `storage_backend: yaml` to use a file instead (mainly for tests).

Legacy `workflow.ini` remains a read-only reference (`legacy/settings/workflow.ini`). `WorkflowIniInspector::getLegacyIniOperations()` / `getLegacyIniEventTypes()` expose raw ini values for documentation and tooling only.

## Troubleshooting: workflow not running on publish

1. **Both bundles enabled** in `config/bundles.php`: `LegacyWorkflowBundle` and any extension (e.g. `OnPublishAfterWorkflowBundle`) **after** it.
2. **Project config**: `config/packages/ibexa_legacy_workflow.yaml` with `enabled: true` and routes imported.
3. **Symfony cache cleared** after adding bundles: `php bin/console cache:clear`.
4. **Event type registered**: `php bin/console debug:container OnPublishAfterEventType` must show tag `haeretici.legacy_workflow.event_type`.
5. **Workflow enabled** in admin (checkbox on workflow edit). Unchecked + Save previously disabled the workflow silently; this is fixed to preserve the current state when the checkbox is omitted.
6. **Trigger assigned**: Settings → Triggers → `content_publish` → **after** → your workflow. Persists to `ibexa_setting` (`group=ibexa_legacy_workflow`, `identifier=workflow_data`).
7. **Status endpoint**: `GET /ibexa_legacy_workflow/status` returns `enabled`, registered `event_types`, `triggers`, and `workflows`.
8. **Logs**: if an event type string is in YAML but not in the container registry, `WorkflowProcessRunner` logs a warning and skips the event (no `var_dump`, no failure).

## Adding a new native operation

1. Add the legacy operation name to `ibexa_legacy_workflow.available_operations`.
2. Implement an `EventSubscriberInterface` that listens to the Ibexa before/after events and calls `TriggerRunner::runTrigger()` with the correct trigger name (`pre_*` / `post_*`), module, function, and parameter keys.
3. Document the mapping in this file.
4. Add PHPUnit coverage for the subscriber + trigger runner path.