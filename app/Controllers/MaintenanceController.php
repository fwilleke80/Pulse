<?php

/**
 * @file MaintenanceController.php
 * @brief Administrative backup/export and recovery request handlers.
 */

declare(strict_types=1);

namespace Pulse\Controllers;

use Pulse\Core\Logger;
use Pulse\Core\Request;
use Pulse\Core\Session;
use Pulse\Core\View;
use Pulse\Services\AuthService;
use Pulse\Services\BackupService;
use Pulse\Services\BackupRestoreService;
use Pulse\Services\TextDocumentExportService;
use Pulse\Services\UpdateInstallerService;
use Pulse\Services\UpdatePackageInspector;
use Throwable;

/** @brief Provides superuser-only operational archives and updates without an ownership bypass. */
final class MaintenanceController extends BaseController
{
	private BackupService $_backupService;
	private TextDocumentExportService $_documentExport;
	private BackupRestoreService $_restoreService;
	private UpdateInstallerService $_updateInstaller;
	private string $_version;

	/**
	 * @brief Constructs the maintenance request handler.
	 * @param View $view View renderer.
	 * @param Session $session Session manager.
	 * @param AuthService $auth Authentication service.
	 * @param Logger $logger Audit logger.
	 * @param Request $request HTTP request.
	 * @param BackupService $backupService Full backup builder.
	 * @param TextDocumentExportService $documentExport Document export builder.
	 * @param BackupRestoreService $restoreService Verified restore handler.
	 * @param UpdateInstallerService $updateInstaller Application ZIP updater.
	 * @param string $version Running Pulse version.
	 */
	public function __construct(View $view, Session $session, AuthService $auth, Logger $logger, Request $request,
		BackupService $backupService, TextDocumentExportService $documentExport, BackupRestoreService $restoreService, UpdateInstallerService $updateInstaller, string $version)
	{
		parent::__construct($view, $session, $auth, $logger, $request);
		$this->_backupService = $backupService;
		$this->_documentExport = $documentExport;
		$this->_restoreService = $restoreService;
		$this->_updateInstaller = $updateInstaller;
		$this->_version = $version;
	}

	/** @brief Downloads the superuser's Markdown documents without exporting other accounts. */
	public function ExportTextDocuments(): void
	{
		$user = $this->RequireSuperUser();
		$temporary = $this->_backupService->TemporaryDirectory();
		$failed = false;
		try
		{
			$archive = $temporary . '/documents.zip';
			$count = $this->_documentExport->ExportForUser((int)$user['id'], $archive);
			$this->_logger->Info('Account exported text documents', ['user_id' => (int)$user['id'], 'count' => $count]);
			$this->Download($archive, 'Pulse_Text_Documents_' . gmdate('Y-m-d_His') . '.zip');
		}
		catch (Throwable $exception)
		{
			$this->_logger->Warning('Text document export failed', ['error' => $exception->getMessage()]);
			$this->Flash('error', __('administration.backup.failed'));
			$failed = true;
		}
		finally
		{
			$this->_backupService->DeleteDirectory($temporary);
		}
		if ($failed)
		{
			$this->Redirect('/administration/maintenance/backups');
		}
	}

	/** @brief Downloads a complete backup, optionally encrypted with the supplied password. */
	public function CreateBackup(): void
	{
		$user = $this->RequireSuperUser();
		$password = $this->_request->PostString('backup_password', 1024, false);
		$encrypt = $this->_request->PostBool('encrypt_backup');
		if ($encrypt && strlen($password) < 12)
		{
			$this->Flash('error', __('administration.backup.password_short'));
			$this->Redirect('/administration/maintenance/backups');
		}
		$temporary = $this->_backupService->TemporaryDirectory();
		$failed = false;
		try
		{
			$archive = $temporary . '/full.zip';
			$manifest = $this->_backupService->Create($archive, $encrypt ? $password : '', $this->_version);
			$this->_logger->Info('Administrator downloaded full backup', [
				'user_id' => (int)$user['id'],
				'encrypted' => $encrypt,
				'files' => count($manifest['files']),
			]);
			$this->Download($archive, 'Pulse_Backup_' . gmdate('Y-m-d_His') . '.zip');
		}
		catch (Throwable $exception)
		{
			$this->_logger->Warning('Complete backup generation failed', ['user_id' => (int)$user['id'], 'error' => $exception->getMessage()]);
			$this->Flash('error', __('administration.backup.failed'));
			$failed = true;
		}
		finally
		{
			$this->_backupService->DeleteDirectory($temporary);
		}
		if ($failed)
		{
			$this->Redirect('/administration/maintenance/backups');
		}
	}

	/** @brief Verify and privately stage a backup without touching live data. */
	public function PreflightRestore(): void
	{
		$user = $this->RequireSuperUser();
		$upload = null;
		$this->DiscardPreflight('restore');
		try
		{
			$upload = $this->UploadedArchive('backup_file', 'restore');
			$password = $this->_request->PostString('restore_password', 1024, false);
			$preflight = $this->_restoreService->Preflight($upload, $password, $this->_version);
			$this->StorePreflight('restore', $upload, $preflight, (int)$user['id']);
			$this->Flash('success', __('administration.preflight.passed'));
		}
		catch (Throwable $exception)
		{
			if (is_string($upload))
			{
				@unlink($upload);
			}
			$this->_logger->Warning('Backup restore preflight failed', ['error' => $exception->getMessage()]);
			$this->Flash('error', __('administration.preflight.failed') . ' ' . $exception->getMessage());
		}
		$this->Redirect('/administration/maintenance/backups');
	}

	/** @brief Restore the verified staged package after fresh password and destructive confirmation. */
	public function RestoreBackup(): void
	{
		$user = $this->RequireSuperUser();
		if (!$this->_request->PostBool('confirm_restore'))
		{
			$this->Flash('error', __('administration.backup.restore_invalid'));
			$this->Redirect('/administration/maintenance/backups');
		}
		$this->VerifyFreshPassword($user, 'backup');
		try
		{
			$upload = $this->PendingArchive('restore', (int)$user['id']);
			$password = $this->_request->PostString('restore_password', 1024, false);
			$this->_restoreService->Preflight($upload, $password, $this->_version);
			$this->_session->Remove('pulse_restore_preflight');
			$manifest = $this->_restoreService->Restore($upload, $password, $this->_version);
			@unlink($upload);
			$this->_logger->Warning('Administrator restored an earlier full system snapshot', [
				'user_id_before_restore' => (int)$user['id'],
				'backup_created_at' => $manifest['created_at'] ?? '',
			]);
			$this->Redirect('/login');
		}
		catch (Throwable $exception)
		{
			$this->_logger->Warning('Backup restore failed', ['error' => $exception->getMessage()]);
			$this->Flash('error', __('administration.backup.restore_failed') . ' ' . $exception->getMessage());
			$this->Redirect('/administration/maintenance/backups');
		}
	}

	/** @brief Re-enables monitoring only after an authenticated superuser has reviewed the restore. */
	public function ResumeAfterRestore(): void
	{
		$user = $this->RequireSuperUser();
		if ($this->_request->PostString('resume_confirmation', 16) !== 'RESUME')
		{
			$this->Flash('error', __('administration.backup.restore_invalid'));
			$this->Redirect('/administration/maintenance/backups');
		}
		try
		{
			$this->_restoreService->Resume();
			$this->_logger->Warning('Administrator resumed monitoring after system restore', ['user_id' => (int)$user['id']]);
			$this->Flash('success', __('administration.backup.resumed'));
		}
		catch (Throwable $exception)
		{
			$this->_logger->Warning('Could not resume monitoring', ['error' => $exception->getMessage()]);
			$this->Flash('error', __('administration.backup.restore_failed'));
		}
		$this->Redirect('/administration/maintenance/backups');
	}

	/** @brief Stage and validate a release ZIP before presenting release notes to its administrator. */
	public function PreflightUpdate(): void
	{
		$user = $this->RequireSuperUser();
		$upload = null;
		$this->DiscardPreflight('update');
		try
		{
			$upload = $this->UploadedArchive('update_file', 'update');
			$report = (new UpdatePackageInspector(dirname(__DIR__, 2)))->Inspect($upload, $this->_version);
			$this->StorePreflight('update', $upload, $report, (int)$user['id']);
			$this->Flash('success', __('administration.preflight.passed'));
		}
		catch (Throwable $exception)
		{
			if (is_string($upload))
			{
				@unlink($upload);
			}
			$this->_logger->Warning('Update preflight failed', ['error' => $exception->getMessage()]);
			$this->Flash('error', __('administration.preflight.failed') . ' ' . $exception->getMessage());
		}
		$this->Redirect('/administration/maintenance/updates');
	}

	/** @brief Discard a verified staged ZIP before selecting another package. */
	public function ChangeUpdatePackage(): void
	{
		$this->RequireSuperUser();
		$this->DiscardPreflight('update');
		$this->Redirect('/administration/maintenance/updates');
	}

	/** @brief Apply the previously inspected and unchanged release ZIP after fresh authentication. */
	public function InstallUpdate(): void
	{
		$user = $this->RequireSuperUser();
		$this->VerifyFreshPassword($user, 'updates');
		try
		{
			$upload = $this->PendingArchive('update', (int)$user['id']);
			$this->_session->Remove('pulse_update_preflight');
			$result = $this->_updateInstaller->Install($upload, $this->_version);
			@unlink($upload);
			$this->_logger->Warning('Administrator installed Pulse application ZIP', [
				'user_id' => (int)$user['id'],
				'from' => $this->_version,
				'to' => $result['installed_version'],
				'migrations' => $result['migrations'],
			]);
			$this->Flash('success', __('administration.updates.success', ['version' => (string)$result['installed_version']]));
		}
		catch (Throwable $exception)
		{
			$this->_logger->Warning('Pulse ZIP update failed', ['user_id' => (int)$user['id'], 'error' => $exception->getMessage()]);
			$this->Flash('error', __('administration.updates.failed') . ' ' . $exception->getMessage());
		}
		$this->Redirect('/administration/maintenance/updates');
	}

	/** @brief Accept a PHP upload into a private, random, non-web-accessible staging path. */
	private function UploadedArchive(string $field, string $kind): string
	{
		$upload = $this->_request->UploadedFile($field);
		if (!is_array($upload) || (int)($upload['error'] ?? -1) !== UPLOAD_ERR_OK
			|| !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name']))
		{
			$this->Flash('error', __('administration.updates.invalid'));
			$this->Redirect(($kind === 'restore' ? '/administration/maintenance/backups' : '/administration/maintenance/updates'));
		}
		$directory = dirname(__DIR__, 2) . '/storage/maintenance-staging';
		if (!is_dir($directory) && !mkdir($directory, 0700, true))
		{
			throw new \RuntimeException('Unable to create private staging directory.');
		}
		// Clear expired uploads without disturbing still-valid preflight sessions.
		foreach (glob($directory . '/*.zip') ?: [] as $oldPath)
		{
			if (is_file($oldPath) && !is_link($oldPath) && filemtime($oldPath) < time() - 7200)
			{
				@unlink($oldPath);
			}
		}
		$target = $directory . '/' . $kind . '-' . bin2hex(random_bytes(20)) . '.zip';
		if (!move_uploaded_file((string)$upload['tmp_name'], $target))
		{
			throw new \RuntimeException('Unable to stage uploaded archive.');
		}
		@chmod($target, 0600);
		return $target;
	}

	/** @brief Discard an earlier archive so failed second preflights cannot install an older package. */
	private function DiscardPreflight(string $kind): void
	{
		$key = 'pulse_' . $kind . '_preflight';
		$old = $this->_session->Get($key);
		if (is_array($old) && is_string($old['path'] ?? null))
		{
			$prefix = dirname(__DIR__, 2) . '/storage/maintenance-staging/' . $kind . '-';
			if (str_starts_with($old['path'], $prefix))
			{
				@unlink($old['path']);
			}
		}
		$this->_session->Remove($key);
	}

	/** @brief Persist only server-derived preflight state; replace and delete older staged uploads. */
	private function StorePreflight(string $kind, string $path, array $report, int $userId): void
	{
		$key = 'pulse_' . $kind . '_preflight';
		$old = $this->_session->Get($key);
		if (is_array($old) && is_string($old['path'] ?? null))
		{
			@unlink($old['path']);
		}
		$this->_session->Set($key, [
			'path' => $path, 'sha256' => hash_file('sha256', $path),
			'user_id' => $userId, 'expires_at' => time() + 1800, 'report' => $report,
		]);
	}

	/** @brief Resolve a session-bound staging path after expiry, owner, and integrity checks. */
	private function PendingArchive(string $kind, int $userId): string
	{
		$state = $this->_session->Get('pulse_' . $kind . '_preflight');
		$path = is_array($state) ? ($state['path'] ?? null) : null;
		$directory = dirname(__DIR__, 2) . '/storage/maintenance-staging/';
		if (!is_string($path) || !str_starts_with($path, $directory . $kind . '-')
			|| (int)($state['user_id'] ?? -1) !== $userId || (int)($state['expires_at'] ?? 0) <= time()
			|| !is_file($path) || is_link($path)
			|| !hash_equals((string)($state['sha256'] ?? ''), hash_file('sha256', $path)))
		{
			throw new \RuntimeException('The prepared archive is missing, expired or changed. Run preflight again.');
		}
		return $path;
	}

	/** @brief Requires current administrator password again for destructive operations. */
	private function VerifyFreshPassword(array $user, string $tab = 'backup'): void
	{
		$attempts = (int)$this->_session->Get('pulse_maintenance_password_attempts', 0);
		$blockedUntil = (int)$this->_session->Get('pulse_maintenance_password_blocked_until', 0);
		if ($blockedUntil > time())
		{
			$this->Flash('error', __('administration.backup.reauthentication_failed'));
			$this->Redirect(($tab === 'backup' ? '/administration/maintenance/backups' : '/administration/maintenance/updates'));
		}
		$attempts = $blockedUntil > 0 && $blockedUntil <= time() ? 0 : $attempts;
		if (!password_verify($this->_request->PostString('administrator_password', 1024, false), (string)($user['password_hash'] ?? '')))
		{
			$attempts++;
			$this->_session->Set('pulse_maintenance_password_attempts', $attempts);
			if ($attempts >= 5)
			{
				$this->_session->Set('pulse_maintenance_password_blocked_until', time() + 900);
			}
			$this->_logger->Warning('Administrator failed maintenance reauthentication', ['user_id' => (int)$user['id']]);
			$this->Flash('error', __('administration.backup.reauthentication_failed'));
			$this->Redirect(($tab === 'backup' ? '/administration/maintenance/backups' : '/administration/maintenance/updates'));
		}
		$this->_session->Remove('pulse_maintenance_password_attempts');
		$this->_session->Remove('pulse_maintenance_password_blocked_until');
	}

	/** @brief Streams an archive with private and no-cache response headers. */
	private function Download(string $path, string $name): void
	{
		if (!is_file($path) || !is_readable($path))
		{
			throw new \RuntimeException('Export file is missing.');
		}
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $name . '"');
		header('Content-Length: ' . (string)filesize($path));
		header('Cache-Control: no-store, private');
		header('X-Content-Type-Options: nosniff');
		if (readfile($path) === false)
		{
			throw new \RuntimeException('Backup download failed.');
		}
	}
}
