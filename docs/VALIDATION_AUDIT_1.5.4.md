# Validation audit — Pulse 1.5.4

| Area | Saving | Diagnostics / safeguards |
| --- | --- | --- |
| Owner due notice and reminders | Incomplete subject/body pairs save as drafts. Empty pairs select the built-in default. | Field, tab and saved Overview diagnostics. Invalid drafts are not queued. |
| Default recipient mail, every language | Invalid pairs or portal-link placement save as drafts. | One source/field issue with affected recipients; exact language/field links. Existing release blocker retained. |
| Personal recipient mail | Invalid drafts remain saveable; disabled personal overrides do not affect delivery. | Inline field markers, recipient Overview and source links. Preview shows invalid saved text instead of silently showing a valid fallback. |
| Safety invitation and reminders | Incomplete pairs or missing/misplaced portal links save as drafts. | Live language/field markers. Gate creation validates all effective invitation/reminder templates before progressing. Reminder staging also checks its snapshot. |
| Portal text | Optional blank content remains valid. | Empty optional personal portal text no longer triggers the inconsistent rejection. Expiry bounds remain hard constraints. |
| Monitor configuration and contacts | Required identifiers, valid addresses and numeric bounds remain required. | Native invalid fields get inline explanations and tab markers; their containing tabs open. These constraints are not converted into unsafe stored values. |
| Text documents | Required title/content remain required. | Native errors reveal the Markdown Edit panel and preserve input; existing local draft recovery remains available. No new published/unfinished document state is introduced. |
| Uploaded documents | Missing files, unsupported types, size limits and ownership violations remain rejected. | Existing per-file error feedback and unsaved-field preservation remain intact. |
| Assignment and released-content editing | Ownership, archived and snapshot protections remain enforced. | Shared native field presentation applies; no authorization bypass or snapshot rewrite is introduced. |

## Saved versus edited state

Live field and tab markers reflect the current form contents. Overview summaries describe persisted values and therefore remain until saving/reloading. Fixing a field is not a claim that a saved release blocker is resolved. Invalid unused language variants are listed as draft problems, without claiming every recipient is affected. A valid built-in fallback is not an error.

Existing valid queued message snapshots are not revoked merely because their source template is subsequently edited. The new guards prevent staging invalid templates. Already delivered mail cannot be recalled.

## Validation performed

- Focused PHP tests cover mail diagnostics, draft persistence across all five template families, owner staging validation, notification language fallback, personal override preservation, existing notification composition, authentication/privacy, assignment deltas and emergency resume.
- DOM checks on rendered monitor HTML cover exact deep links, field/subsection/language markers, correction behavior, saveability and the saved-versus-draft distinction. Personal and inherited recipient views render with warnings treated as errors and show their source links.
- Existing document drop and draft-recovery checks were repeated. PHP/JavaScript syntax and release archive checks were performed.
- PHP validation used 8.3 with PHPUnit 9.6; supported deployment remains PHP 8.4+. No full browser/HTTP/SMTP end-to-end run was performed. The previously documented full-suite source-assertion failures are not claimed resolved. Validate delivery scheduling and editor interaction on the target stack before relying on this change in production.
