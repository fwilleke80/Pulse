# Pulse 1.5.0

## Upgrade from 1.4.7

Back up the database and installation, then install the update ZIP through the existing updater. Migration 011 preserves existing accounts as approved and verified and makes the earliest installed account the protected superuser. A fresh installation creates the protected superuser directly. Existing sessions must sign in again. Public registration starts disabled.

For a monitor stuck as Paused after emergency-stop testing, sign in and choose **Resume** from its dashboard menu or editor. Resume atomically clears both flags and starts a fresh interval. The migration itself leaves affected monitors paused. Previously cancelled messages and revoked recipient portals are not restored. Global emergency reactivation preserves pauses that predated the emergency.

## Account behavior

- Normal users have access to their own content. Administrators also manage users and registration settings. Neither administrators nor the superuser gain content-browsing access to other users' documents, monitors, recipients or private mail details.
- The original superuser cannot be deleted, demoted or disabled through user management. Other administrators cannot alter that account's credentials. Installation backup, restore and update functions require the superuser.
- Server operators and anyone holding full backups can access stored installation data. Account privacy is enforced by application authorization; this release does not introduce end-to-end encryption or protection against a malicious server operator. Administrators' power to reset normal users' credentials is likewise a trusted operational privilege.
- Administrators may provision a password directly; no invitation email is sent automatically. Use the explicit invitation button if desired. A directly provisioned password must be changed on first login. An account created without a password needs an invitation before it can be used.
- Public registrations always create normal users, require email confirmation, and also require approval when that setting is enabled. Clicking the email link opens an explicit confirmation action so link scanners cannot consume it merely by visiting.
- Administrators can require two-factor enrollment or reset a lost TOTP setup. Resetting required TOTP does not waive enrollment. Security changes invalidate existing sessions and pending authentication challenges.
- Blocking account login does not pause that user's monitoring. Deleting an account removes its content and pending work; the deletion screen requires confirmation.
- Forms use CSRF protection and escaped output. Public authentication actions are throttled; account links are expiring, single-use and stored as hashes. Keep the existing HTTPS and server-hardening requirements.

## Document uploads

Drop one or more eligible files in either editor's Documents tab. Each successful upload uses the full file name as its document name. Recipient-editor uploads are attached to that recipient. Existing validation and size/type restrictions still apply. Errors are reported per file, and unsaved editor changes remain in place. Keep the page open until uploads finish.

## Validation

- Focused PHP suite: 65 tests, 117 assertions passed, including account lifecycle, authentication, TOTP, redirects, ownership boundaries and emergency-resume transactions. The privacy harness exercises 144 role/controller checks.
- Emergency tests cover the original inconsistent state, cancellation of lingering cycle mail, a fresh interval, repeated Resume, owner isolation, pre-existing pause preservation and rollback/retry after a failed cycle insert.
- MariaDB 10.11 bootstrap tests apply all migrations to a 1.4.7-style database and verify protected-owner migration and safe repair of a legacy inconsistent emergency flag. Fresh-schema, token consumption and account-delete SQL checks also pass.
- DOM tests cover multi-file drops in both editors, rejected files, escaped filenames and preservation of unsaved editor state. Existing document-draft recovery tests pass. PHP syntax and JavaScript syntax checks pass.
- The complete unit suite has seven failures and one error also present in the untouched 1.4.7 baseline. They are stale source/layout/schema assertions and an undefined variable in RecipientDocumentManagementSourceTest. The affected assertion classes are AdministrationSourceTest, BaselineIntegritySourceTest, EditorUiConsistencySourceTest, MonitorOperationalOverviewSourceTest, NotificationInfrastructureSourceTest, PortalAuditRegressionSourceTest and SecurityFoundationTest. This is not a claim that the full suite is green.
- Validation used PHP 8.3 and PHPUnit 9.6 because PHP 8.4 was unavailable in this environment; the supported deployment requirement remains PHP 8.4+. No full browser/HTTP/SMTP end-to-end run was performed. Before production reliance, verify on the target PHP/database/web-server stack: installation/upgrade, registration mail and approval, optional invitations, forced password/TOTP setup, two-user isolation, both document drop areas, and emergency stop/resume with scheduled delivery.
