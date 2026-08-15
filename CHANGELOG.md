## 1.0.0 - 2026-08-15

### Stable release
- Established Pulse 1.0.0 on the audited fresh-install database baseline.
- Added **Last successful cron run** to Administration → Cron. Successful combined web-cron and CLI `notifications:run` executions record their completion timestamp.
- Added Cron-tab configuration warnings when no successful run has ever been recorded or when the last successful run is more than 24 hours old.
- Clarified the debug **Force due now** confirmation so it explains that the due notice is queued by the next cron run rather than immediately by the button itself.
- Re-audited and substantially rebuilt the shipped 1.0 documentation against the final interface and runtime behavior: corrected the seven Monitor Editor tabs and five Recipient Editor tabs, current recipient-email `{url}` requirements, archive/delete rules, portal snapshot behavior, Administration, cron semantics, and the stable update model.
- Simplified the user-facing guides and expanded missing end-to-end workflows, including first setup, rehearsal, recipient delivery, safety contacts, history, mail recovery, and operational checks.
- Clarified the actual deployment constraint: Pulse 1.0 expects `public/` at the root of its host or virtual host; URL-prefix deployments such as `/pulse/` are not supported by the built-in router.
- Added a repeatable `docs/AUDIT_GUIDE.md` for installation, lifecycle, portal-security, queue/concurrency, integrity, localization, and release verification.

### Verification
- Final source, schema, configuration, translation, security, documentation, and package checks completed against the clean 1.0 baseline.

## 0.9.10 - 2026-08-15

### 1.0 baseline cleanup
- Squashed the complete pre-release database history into a single `001_initial_schema.sql` containing the current Pulse schema.
- Removed pre-release migration baselining and compatibility branches from the migration runner. Future schema changes will build forward from the 1.0 baseline.
- Removed obsolete migration-specific references, pre-release compatibility guidance, and now-dead delivery-retention cleanup code from the current baseline.
- Reset the current documentation to describe the clean 1.0-era installation, configuration, architecture, and update model.
- Added mail-deliverability guidance recommending safe-sender/allowlist or whitelist entries for the configured Pulse sender, alongside normal SPF/DKIM/DMARC setup.

### Data integrity
- Monitors with recipient release history can no longer be deleted. Released delivery/history is preserved; use the monitor lifecycle/archive model instead.
- Contacts that are still assigned as a recipient or safety contact on any monitor can no longer be deleted until those assignments are removed or changed.
- Added restrictive foreign-key rules to the baseline schema as a database-level backstop for current monitor/contact assignments and released monitor history.

### Interface
- Further refined Monitor Editor → Recipients so language, email-text source, and document count share the available metadata width naturally instead of leaving a trailing empty column.
- Contact and monitor action menus now reflect the new deletion guards.

### Development
- Version bumped to 0.9.10.
- This build establishes the database/source baseline intended for the final fresh-install audit before Pulse 1.0.0.
