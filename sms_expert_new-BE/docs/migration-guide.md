# Customer Migration Guide — Parent & Sub-Accounts

This guide explains how the **old → new system** customer migration works on the
admin customers page (`/admin/customers`), including the parent/sub-account options.

## Background

- A customer is a **Sub-Account** when its `users.masteruname` points at another
  customer's `uname`. A **Master (parent)** is a `uname` that one or more
  sub-accounts point to. (Same rule the customer detail page uses for its
  "Sub Accounts" panel.)
- On the customers list, sub-accounts are shown **nested under their parent**
  (a collapsible tree). Click a master's **▸ Master · N sub** badge to expand.
- Migration sets the account's `migration_flag = 'new'` and stamps
  `migrated_at`, repoints inbound (MO) webhooks to the new system, and
  optionally queues campaign-file migration in the background.
- Accounts already on the new system show a **Migrated** badge and are skipped —
  they cannot be re-migrated.

## Where to migrate

1. **Per-master Migrate button** (Options column) — opens the migration modal
   with the 5 options below. This is the main way to migrate a parent and/or its
   sub-accounts together.
2. **Bulk toolbar** (on the *Not Migrated* tab) — "Migrate Selected" / "Migrate
   All". Tick a parent **and** its sub-accounts to move them together.

## The 5 migration options

When you click **Migrate** on a master account, choose one:

| # | Option | What it migrates |
|---|--------|------------------|
| 1 | **Only the parent account** | Just the master. Sub-accounts stay on the old system. |
| 2 | **Only selected sub-account(s)** | Only the sub-accounts you tick. Parent untouched. |
| 3 | **All sub-accounts** | Every sub-account of this parent, but not the parent. |
| 4 | **Parent + all sub-accounts** | The whole group — master and every sub-account. |
| 5 | **Parent + selected sub-account(s)** | The parent plus only the sub-accounts you tick. |

- For the **"selected"** options (2 and 5) the sub-account checkboxes become
  editable so you can pick exactly which ones to migrate.
- For the other options (1, 3, 4) the checkboxes are set automatically and locked
  to match the option.
- Already-migrated accounts are shown disabled and are never included.

## Tip: mixed migration states

A parent and its sub-accounts can be in different states (e.g. the parent already
migrated while some subs are still on the old system). In that case:

- The parent's **Migrate** button still appears as long as *anything* in the
  group is un-migrated.
- Use **Option 2 (only selected subs)** or **Option 3 (all subs)** to migrate the
  remaining sub-accounts without touching the already-migrated parent.

## What happens on migrate (technical)

For each selected, un-migrated account the system:

1. Sets `migration_flag = 'new'` and `migrated_at = now()` (only for
   `migration_flag = 'old'`/NULL, `bit_disabled = 0`, customer login type).
2. Queues a Nexmo virtual-number webhook repoint to the new-system inbound URL
   (Sinch numbers self-skip).
3. If "Also migrate campaign files" is ticked, queues a campaign-file migration
   batch (`old_to_new`) processed in the background; the response includes a
   Batch ID.

Endpoint: `POST /admin/customers/bulk-migrate`
(`AdminUserController@bulkMigrate`) — accepts `customer_ids` (array of user IDs
or `"all"`) and `migrate_campaign_files` (bool).
