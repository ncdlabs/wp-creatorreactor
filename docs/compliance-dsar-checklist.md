# CreatorReactor DSAR Checklist

This checklist supports operational handling of CCPA/CPRA and GDPR data subject requests.

## Request intake

- Verify requestor identity using your existing site process.
- Confirm request type: access/export, deletion/erasure, correction.
- Record request date and legal deadline in your ticketing/compliance tracker.

## Export request (access/portability)

- Go to `Tools -> Export Personal Data` in WordPress admin.
- Run export for the requestor email.
- Confirm export includes CreatorReactor groups:
  - `CreatorReactor User Meta`
  - `CreatorReactor Entitlements`
  - `CreatorReactor Pending Registration` (if present)
- Deliver export per your legal process.

## Erasure request (deletion)

- Go to `Tools -> Erase Personal Data` in WordPress admin.
- Run erasure for the requestor email.
- Confirm CreatorReactor data was removed:
  - onboarding/contact meta
  - social linkage/meta snapshots
  - entitlements rows linked by email or user ID
  - pending onboarding rows
  - email-linked sync/connection log entries

## Correction request

- Update profile fields in WordPress user profile and any mapped CreatorReactor meta.
- If entitlement-linked identifiers changed externally, run sync and verify corrected state.

## Post-processing verification

- Re-run export and verify corrected/erased output.
- Confirm no residual records in custom admin debug views.
- Record completion timestamp and operator.

## Retention and housekeeping

- Confirm retention settings in `creatorreactor_settings`:
  - `privacy_log_retention_days`
  - `privacy_profile_snapshot_retention_days`
- Confirm daily purge cron is scheduled for:
  - `creatorreactor_privacy_retention_purge`

