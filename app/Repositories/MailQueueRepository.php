<?php

/**
 * @file MailQueueRepository.php
 * @brief Durable transactional mail queue with expiring worker leases.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Pulse\Core\Database;
use Throwable;

/**
 * @brief Persists immutable messages and coordinates concurrent queue workers.
 */
final class MailQueueRepository
{
	private Database $_database;

	/** @brief Constructs the repository. @param Database $database Database service. */
	public function __construct(Database $database)
	{
		$this->_database = $database;
	}

	/**
	 * @brief Enqueues an immutable message exactly once for its idempotency key.
	 * @param array<string, mixed> $message Queue fields.
	 * @return int Queue job ID.
	 */
	public function Enqueue(array $message): int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$statement = $connection->prepare('
				INSERT INTO mail_queue
				(
					user_id,
					check_cycle_id,
					monitor_id,
					contact_id,
					safety_request_id,
					recipient_delivery_id,
					recipient_portal_code_id,
					mail_type,
					idempotency_key,
					reminder_number,
					recipient_email,
					subject,
					body_text,
					status,
					attempt_count,
					max_attempts,
					available_at,
					created_at,
					updated_at
				)
				VALUES
				(
					:user_id,
					:check_cycle_id,
					:monitor_id,
					:contact_id,
					:safety_request_id,
					:recipient_delivery_id,
					:recipient_portal_code_id,
					:mail_type,
					:idempotency_key,
					:reminder_number,
					:recipient_email,
					:subject,
					:body_text,
					\'queued\',
					0,
					:max_attempts,
					:available_at,
					UTC_TIMESTAMP(),
					UTC_TIMESTAMP()
				)
				ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
			');
			$statement->execute([
				'user_id' => (int)$message['user_id'],
				'check_cycle_id' => $message['check_cycle_id'] ?? null,
				'monitor_id' => $message['monitor_id'] ?? null,
				'contact_id' => $message['contact_id'] ?? null,
				'safety_request_id' => $message['safety_request_id'] ?? null,
				'recipient_delivery_id' => $message['recipient_delivery_id'] ?? null,
				'recipient_portal_code_id' => $message['recipient_portal_code_id'] ?? null,
				'mail_type' => (string)$message['mail_type'],
				'idempotency_key' => (string)$message['idempotency_key'],
				'reminder_number' => $message['reminder_number'] ?? null,
				'recipient_email' => (string)$message['recipient_email'],
				'subject' => (string)$message['subject'],
				'body_text' => (string)$message['body_text'],
				'max_attempts' => (int)$message['max_attempts'],
				'available_at' => (string)$message['available_at'],
			]);

			$lookup = $connection->prepare('SELECT id FROM mail_queue WHERE idempotency_key = :key LIMIT 1');
			$lookup->execute(['key' => (string)$message['idempotency_key']]);
			$jobId = (int)$lookup->fetchColumn();

			if ($jobId <= 0)
			{
				throw new \RuntimeException('The queued message could not be resolved.');
			}

			$connection->commit();
			return $jobId;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Recovers jobs abandoned after a worker crash.
	 * @return int Number of expired leases recovered.
	 */
	public function RecoverExpiredLeases(): int
	{
		$statement = $this->_database->GetConnection()->prepare('
			UPDATE mail_queue
			SET
				status = CASE WHEN attempt_count >= max_attempts THEN \'failed\' ELSE \'retrying\' END,
				last_error = \'The previous worker lease expired before completion.\',
				failed_at = CASE WHEN attempt_count >= max_attempts THEN UTC_TIMESTAMP() ELSE NULL END,
				available_at = CASE WHEN attempt_count >= max_attempts THEN available_at ELSE UTC_TIMESTAMP() END,
				locked_at = NULL,
				locked_until = NULL,
				locked_by = NULL,
				lease_token = NULL,
				updated_at = UTC_TIMESTAMP()
			WHERE status = \'processing\'
				AND locked_until < UTC_TIMESTAMP()
		');
		$statement->execute();
		$recovered = $statement->rowCount();

		$invalidate = $this->_database->GetConnection()->prepare('
			UPDATE recipient_portal_codes rpc
			INNER JOIN mail_queue mq ON mq.recipient_portal_code_id = rpc.id
			SET rpc.invalidated_at = COALESCE(rpc.invalidated_at, UTC_TIMESTAMP()), rpc.updated_at = UTC_TIMESTAMP(),
				mq.body_text = \'[Access code redacted after final lease failure]\', mq.updated_at = UTC_TIMESTAMP()
			WHERE mq.mail_type = \'recipient_access_code\'
				AND mq.status = \'failed\'
				AND rpc.used_at IS NULL
		');
		$invalidate->execute();

		return $recovered;
	}

	/**
	 * @brief Claims a batch with row locks that skip jobs held by other workers.
	 * @param string $workerId Unique worker run ID.
	 * @param int $limit Maximum jobs.
	 * @param int $leaseSeconds Lease duration.
	 * @return array<int, array<string, mixed>>
	 */
	public function ClaimBatch(string $workerId, int $limit, int $leaseSeconds): array
	{
		return $this->Claim($workerId, max(1, min(250, $limit)), $leaseSeconds, null);
	}

	/**
	 * @brief Claims one specific queued job, used by the interactive test action.
	 * @return array<string, mixed>|null
	 */
	public function ClaimById(int $jobId, string $workerId, int $leaseSeconds): ?array
	{
		$jobs = $this->Claim($workerId, 1, $leaseSeconds, $jobId);
		return $jobs[0] ?? null;
	}


	/**
	 * @brief Makes one queued debug mail eligible for an immediate delivery attempt.
	 *
	 * Retry backoff is deliberately bypassed only for an explicit debug action. A
	 * permanently failed job is re-opened with a fresh attempt budget so the
	 * operator can test again after correcting SMTP configuration.
	 *
	 * @param int $jobId Queue job ID.
	 * @return bool True when the job is eligible for an immediate retry.
	 */
	public function PrepareImmediateDebugRetry(int $jobId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('
			UPDATE mail_queue
			SET
				status = CASE WHEN status = \'failed\' THEN \'retrying\' ELSE status END,
				attempt_count = CASE WHEN status = \'failed\' THEN 0 ELSE attempt_count END,
				available_at = UTC_TIMESTAMP(),
				failed_at = CASE WHEN status = \'failed\' THEN NULL ELSE failed_at END,
				locked_at = NULL,
				locked_until = NULL,
				locked_by = NULL,
				lease_token = NULL,
				updated_at = UTC_TIMESTAMP()
			WHERE id = :id
				AND status IN (\'queued\', \'retrying\', \'failed\')
		');
		$statement->execute(['id' => $jobId]);

		return $statement->rowCount() === 1;
	}

	/**
	 * @brief Checks whether a claimed owner notification still belongs to an awaiting active cycle.
	 * @param array<string, mixed> $job Claimed job.
	 */
	public function IsStillDeliverable(array $job): bool
	{
		$mailType = (string)$job['mail_type'];

		if (in_array($mailType, ['owner_due_notice', 'owner_reminder'], true))
		{
			$statement = $this->_database->GetConnection()->prepare('
				SELECT COUNT(*)
				FROM check_cycles cc
				INNER JOIN monitors m ON m.id = cc.monitor_id
				WHERE cc.id = :cycle_id
					AND cc.status = \'awaiting\'
					AND m.is_paused = 0
			');
			$statement->execute(['cycle_id' => (int)$job['check_cycle_id']]);

			return (int)$statement->fetchColumn() === 1;
		}

		if (in_array($mailType, ['safety_invitation', 'safety_reminder'], true))
		{
			$statement = $this->_database->GetConnection()->prepare('
				SELECT COUNT(*)
				FROM safety_contact_requests scr
				INNER JOIN check_cycles cc ON cc.id = scr.check_cycle_id
				INNER JOIN monitors m ON m.id = scr.monitor_id
				WHERE scr.id = :request_id
					AND scr.status = \'pending\'
					AND cc.status = \'safety_pending\'
					AND m.is_paused = 0
			');
			$statement->execute(['request_id' => (int)$job['safety_request_id']]);

			return (int)$statement->fetchColumn() === 1;
		}

		if ($mailType === 'recipient_notification')
		{
			$statement = $this->_database->GetConnection()->prepare('
				SELECT COUNT(*)
				FROM recipient_release_deliveries rrd
				INNER JOIN recipient_releases rr ON rr.id = rrd.release_id
				INNER JOIN check_cycles cc ON cc.id = rrd.check_cycle_id
				INNER JOIN monitors m ON m.id = rrd.monitor_id
				WHERE rrd.id = :delivery_id
					AND rrd.status = \'queued\'
					AND rr.status IN (\'pending\', \'partial\', \'failed\')
					AND cc.status IN (\'overdue\', \'escalated\')
					AND m.is_paused = 0
			');
			$statement->execute(['delivery_id' => (int)$job['recipient_delivery_id']]);

			return (int)$statement->fetchColumn() === 1;
		}


		if ($mailType === 'recipient_access_code')
		{
			$statement = $this->_database->GetConnection()->prepare('
				SELECT COUNT(*)
				FROM recipient_portal_codes rpc
				INNER JOIN recipient_release_deliveries rrd ON rrd.id = rpc.recipient_delivery_id
				WHERE rpc.id = :code_id
					AND rrd.id = :delivery_id
					AND rrd.status = \'sent\'
					AND rrd.portal_released_at IS NOT NULL
					AND rrd.portal_revoked_at IS NULL
					AND (rrd.portal_expires_at IS NULL OR rrd.portal_expires_at > UTC_TIMESTAMP())
					AND rpc.used_at IS NULL
					AND rpc.invalidated_at IS NULL
					AND rpc.expires_at > UTC_TIMESTAMP()
			');
			$statement->execute([
				'code_id' => (int)$job['recipient_portal_code_id'],
				'delivery_id' => (int)$job['recipient_delivery_id'],
			]);

			return (int)$statement->fetchColumn() === 1;
		}

		return true;
	}

	/**
	 * @brief Marks a claimed message delivered and updates reminder state atomically.
	 */
	public function MarkSent(int $jobId, string $workerId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$job = $this->FindClaimedForUpdate($connection, $jobId, $workerId);

			if (!is_array($job))
			{
				$connection->commit();
				return false;
			}

			$now = $this->Now();
			$update = $connection->prepare('
				UPDATE mail_queue
				SET
					status = \'sent\',
					body_text = CASE
						WHEN mail_type IN (\'safety_invitation\', \'safety_reminder\') THEN \'[Safety link redacted after delivery]\'
						WHEN mail_type = \'recipient_notification\' THEN \'[Recipient portal link redacted after delivery]\'
						WHEN mail_type = \'recipient_access_code\' THEN \'[Access code redacted after delivery]\'
						ELSE body_text
					END,
					sent_at = :sent_at,
					last_error = NULL,
					locked_at = NULL,
					locked_until = NULL,
					locked_by = NULL,
					lease_token = NULL,
					updated_at = :updated_at
				WHERE id = :id
			');
			$update->execute(['sent_at' => $now, 'updated_at' => $now, 'id' => $jobId]);
			$this->InsertLog($connection, $job, 'sent', null, $now);

			if ((string)$job['mail_type'] === 'owner_due_notice' && (int)$job['check_cycle_id'] > 0)
			{
				$this->RecordDueNoticeSent($connection, $job, $now);
			}
			elseif ((string)$job['mail_type'] === 'owner_reminder' && (int)$job['check_cycle_id'] > 0)
			{
				$this->RecordReminderSent($connection, $job, $now);
			}
			elseif (in_array((string)$job['mail_type'], ['safety_invitation', 'safety_reminder'], true))
			{
				$this->RecordSafetyMailSent($connection, $job, $now);
			}
			elseif ((string)$job['mail_type'] === 'recipient_notification')
			{
				$this->RecordRecipientNotificationSent($connection, $job, $now);
			}
			elseif ((string)$job['mail_type'] === 'recipient_access_code')
			{
				$this->RecordRecipientAccessCodeSent($connection, $job, $now);
			}

			$connection->commit();
			return true;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Records a failed attempt and schedules an automatic retry or final failure.
	 */
	public function MarkFailedAttempt(int $jobId, string $workerId, string $error, int $retryDelaySeconds): string
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$job = $this->FindClaimedForUpdate($connection, $jobId, $workerId);

			if (!is_array($job))
			{
				$connection->commit();
				return 'ignored';
			}

			$error = substr(trim($error), 0, 2000);
			$final = (int)$job['attempt_count'] >= (int)$job['max_attempts'];
			$status = $final ? 'failed' : 'retrying';
			$now = $this->Now();
			$availableAt = $this->AddSeconds($retryDelaySeconds);
			$update = $connection->prepare('
				UPDATE mail_queue
				SET
					status = :status,
					available_at = :available_at,
					last_error = :last_error,
					failed_at = :failed_at,
					locked_at = NULL,
					locked_until = NULL,
					locked_by = NULL,
					lease_token = NULL,
					updated_at = :updated_at
				WHERE id = :id
			');
			$update->execute([
				'status' => $status,
				'available_at' => $availableAt,
				'last_error' => $error,
				'failed_at' => $final ? $now : null,
				'updated_at' => $now,
				'id' => $jobId,
			]);
			$this->InsertLog($connection, $job, $status, $error, $now);

			if ($final)
			{
				$this->RecordLinkedFinalFailure($connection, $job, $error, $now);
			}
			$connection->commit();

			return $status;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @brief Cancels a claimed job that became obsolete before transport delivery. */
	public function CancelClaim(int $jobId, string $workerId): bool
	{
		$statement = $this->_database->GetConnection()->prepare('
			UPDATE mail_queue
			SET
				status = \'cancelled\',
				body_text = CASE
					WHEN mail_type IN (\'safety_invitation\', \'safety_reminder\') THEN \'[Safety link redacted after cancellation]\'
					WHEN mail_type = \'recipient_notification\' THEN \'[Recipient portal link redacted after cancellation]\'
					WHEN mail_type = \'recipient_access_code\' THEN \'[Access code redacted after cancellation]\'
					ELSE body_text
				END,
				cancelled_at = UTC_TIMESTAMP(),
				locked_at = NULL,
				locked_until = NULL,
				locked_by = NULL,
				lease_token = NULL,
				updated_at = UTC_TIMESTAMP()
			WHERE id = :id
				AND status = \'processing\'
				AND locked_by = :worker_id
		');
		$statement->execute(['id' => $jobId, 'worker_id' => $workerId]);
		return $statement->rowCount() === 1;
	}

	/** @brief Cancels owner notifications that have not yet been claimed for a closed cycle. */
	public function CancelPendingForCycle(int $cycleId): int
	{
		$statement = $this->_database->GetConnection()->prepare('
			UPDATE mail_queue
			SET status = \'cancelled\',
				body_text = CASE
					WHEN mail_type IN (\'safety_invitation\', \'safety_reminder\') THEN \'[Safety link redacted after cancellation]\'
					WHEN mail_type = \'recipient_notification\' THEN \'[Recipient portal link redacted after cancellation]\'
					WHEN mail_type = \'recipient_access_code\' THEN \'[Access code redacted after cancellation]\'
					ELSE body_text
				END,
				cancelled_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
			WHERE check_cycle_id = :cycle_id
				AND mail_type IN (\'owner_due_notice\', \'owner_reminder\', \'safety_invitation\', \'safety_reminder\', \'recipient_notification\')
				AND status IN (\'queued\', \'retrying\', \'failed\')
		');
		$statement->execute(['cycle_id' => $cycleId]);
		return $statement->rowCount();
	}

	/**
	 * @brief Resets a bounded number of permanently failed jobs for explicit operator retry.
	 */
	public function RetryFailed(int $limit): int
	{
		return $this->RetryFailedInternal($limit, null);
	}

	/** @brief Requeues failed messages belonging to one authenticated owner. */
	public function RetryFailedForUser(int $userId, int $limit = 100): int
	{
		return $this->RetryFailedInternal($limit, $userId);
	}

	/** @brief Implements bounded failed-job reset with an optional owner scope. */
	private function RetryFailedInternal(int $limit, ?int $userId): int
	{
		$limit = max(1, min(500, $limit));
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$userFilter = $userId === null ? '' : ' AND user_id = ' . (int)$userId;
			$ids = $connection->query('
				SELECT id
				FROM mail_queue
				WHERE status = \'failed\'
					AND mail_type <> \'recipient_access_code\'
					' . $userFilter . '
				ORDER BY failed_at ASC, id ASC
				LIMIT ' . $limit . '
				FOR UPDATE SKIP LOCKED
			')->fetchAll(PDO::FETCH_COLUMN);

			if (!is_array($ids) || $ids === [])
			{
				$connection->commit();
				return 0;
			}

			$idList = implode(',', array_map('intval', $ids));
			$updated = $connection->exec('
				UPDATE mail_queue
				SET
					status = \'retrying\',
					attempt_count = 0,
					available_at = UTC_TIMESTAMP(),
					failed_at = NULL,
					last_error = NULL,
					updated_at = UTC_TIMESTAMP()
				WHERE id IN (' . $idList . ')
			');
			$connection->exec('
				UPDATE recipient_release_deliveries rrd
				INNER JOIN mail_queue mq ON mq.id = rrd.queue_id
				SET rrd.status = \'queued\', rrd.failed_at = NULL, rrd.last_error = NULL, rrd.updated_at = UTC_TIMESTAMP()
				WHERE mq.id IN (' . $idList . ')
			');
			$connection->exec('
				UPDATE recipient_releases rr
				INNER JOIN recipient_release_deliveries rrd ON rrd.release_id = rr.id
				INNER JOIN mail_queue mq ON mq.id = rrd.queue_id
				SET rr.status = CASE WHEN rr.first_sent_at IS NULL THEN \'pending\' ELSE \'partial\' END,
					rr.completed_at = NULL, rr.updated_at = UTC_TIMESTAMP()
				WHERE mq.id IN (' . $idList . ')
			');
			$connection->commit();
			return (int)$updated;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Returns recent queue entries for one authenticated owner.
	 * @param int $userId Owner user ID.
	 * @param int $limit Maximum number of rows to return.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindRecentForUser(int $userId, int $limit = 50): array
	{
		$limit = max(1, min(200, $limit));
		$statement = $this->_database->GetConnection()->prepare('
			SELECT
				id, mail_type, recipient_email, subject, status, attempt_count, max_attempts,
				last_error, available_at, sent_at, failed_at, cancelled_at, created_at, updated_at
			FROM mail_queue
			WHERE user_id = :user_id
			ORDER BY id DESC
			LIMIT ' . $limit . '
		');
		$statement->execute(['user_id' => $userId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Returns recent queue entries across the installation for an administrator.
	 * @param int $limit Maximum number of rows to return.
	 * @return array<int, array<string, mixed>>
	 */
	public function FindRecent(int $limit = 50): array
	{
		$limit = max(1, min(200, $limit));
		$rows = $this->_database->GetConnection()->query('
			SELECT
				id, user_id, mail_type, recipient_email, subject, status, attempt_count, max_attempts,
				last_error, available_at, sent_at, failed_at, cancelled_at, created_at, updated_at
			FROM mail_queue
			ORDER BY id DESC
			LIMIT ' . $limit . '
		')->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Clears safe unsent owner/test jobs for a debug-mode operator.
	 *
	 * Safety-contact, recipient-notification, and access-code jobs are deliberately
	 * preserved because deleting those immutable jobs can strand an active
	 * escalation or invalidate a credential that cannot be reconstructed.
	 *
	 * @param int $userId Owner user ID.
	 * @return int Number of queue rows removed.
	 */
	public function ClearDebugQueueForUser(int $userId): int
	{
		$statement = $this->_database->GetConnection()->prepare('
			DELETE FROM mail_queue
			WHERE user_id = :user_id
				AND mail_type IN (\'test\', \'owner_due_notice\', \'owner_reminder\')
				AND status IN (\'queued\', \'retrying\', \'failed\', \'cancelled\')
		');
		$statement->execute(['user_id' => $userId]);

		return $statement->rowCount();
	}

	/**
	 * @brief Clears safe unsent owner/test jobs across the installation in debug mode.
	 * @return int Number of queue rows removed.
	 */
	public function ClearDebugQueue(): int
	{
		$statement = $this->_database->GetConnection()->prepare('
			DELETE FROM mail_queue
			WHERE mail_type IN (\'test\', \'owner_due_notice\', \'owner_reminder\')
				AND status IN (\'queued\', \'retrying\', \'failed\', \'cancelled\')
		');
		$statement->execute();

		return $statement->rowCount();
	}

	/** @return array<string, int> @brief Returns queue counts across the installation. */
	public function CountByStatus(): array
	{
		$counts = ['queued' => 0, 'retrying' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
		$rows = $this->_database->GetConnection()->query('
			SELECT status, COUNT(*) AS total
			FROM mail_queue
			GROUP BY status
		')->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rows as $row)
		{
			$counts[(string)$row['status']] = (int)$row['total'];
		}

		return $counts;
	}

	/** @return array<string, int> @brief Returns queue counts for a user. */
	public function CountByStatusForUser(int $userId): array
	{
		$counts = ['queued' => 0, 'retrying' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
		$statement = $this->_database->GetConnection()->prepare('
			SELECT status, COUNT(*) AS total
			FROM mail_queue
			WHERE user_id = :user_id
			GROUP BY status
		');
		$statement->execute(['user_id' => $userId]);

		foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			$counts[(string)$row['status']] = (int)$row['total'];
		}

		return $counts;
	}

	/** @return array<string, mixed>|null @brief Returns a user's latest test notification. */
	public function FindLatestTestForUser(int $userId): ?array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT id, recipient_email, status, attempt_count, last_error, available_at, sent_at, failed_at, created_at
			FROM mail_queue
			WHERE user_id = :user_id AND mail_type = \'test\'
			ORDER BY id DESC
			LIMIT 1
		');
		$statement->execute(['user_id' => $userId]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/** @return array<string, mixed>|null @brief Finds one job for result reporting. */
	public function FindById(int $jobId): ?array
	{
		$statement = $this->_database->GetConnection()->prepare('SELECT * FROM mail_queue WHERE id = :id LIMIT 1');
		$statement->execute(['id' => $jobId]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Performs the shared transactional claim operation.
	 * @return array<int, array<string, mixed>>
	 */
	private function Claim(string $workerId, int $limit, int $leaseSeconds, ?int $jobId): array
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$whereId = $jobId === null ? '' : ' AND id = :job_id';
			$statement = $connection->prepare('
				SELECT id
				FROM mail_queue
				WHERE status IN (\'queued\', \'retrying\')
					AND available_at <= UTC_TIMESTAMP()
					AND attempt_count < max_attempts
					' . $whereId . '
				ORDER BY available_at ASC, id ASC
				LIMIT ' . $limit . '
				FOR UPDATE SKIP LOCKED
			');
			$statement->execute($jobId === null ? [] : ['job_id' => $jobId]);
			$ids = $statement->fetchAll(PDO::FETCH_COLUMN);

			if (!is_array($ids) || $ids === [])
			{
				$connection->commit();
				return [];
			}

			$idList = implode(',', array_map('intval', $ids));
			$leaseToken = bin2hex(random_bytes(16));
			$claim = $connection->prepare('
				UPDATE mail_queue
				SET
					status = \'processing\',
					attempt_count = attempt_count + 1,
					locked_at = UTC_TIMESTAMP(),
					locked_until = TIMESTAMPADD(SECOND, :lease_seconds, UTC_TIMESTAMP()),
					locked_by = :worker_id,
					lease_token = :lease_token,
					updated_at = UTC_TIMESTAMP()
				WHERE id IN (' . $idList . ')
			');
			$claim->execute([
				'lease_seconds' => max(30, min(1800, $leaseSeconds)),
				'worker_id' => substr($workerId, 0, 64),
				'lease_token' => $leaseToken,
			]);
			$jobs = $connection->query('
				SELECT *
				FROM mail_queue
				WHERE id IN (' . $idList . ')
					AND status = \'processing\'
					AND lease_token = ' . $connection->quote($leaseToken) . '
				ORDER BY available_at ASC, id ASC
			')->fetchAll(PDO::FETCH_ASSOC);
			$connection->commit();

			return is_array($jobs) ? $jobs : [];
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @return array<string, mixed>|null @brief Finds a claimed job while holding its row lock. */
	private function FindClaimedForUpdate(PDO $connection, int $jobId, string $workerId): ?array
	{
		$statement = $connection->prepare('
			SELECT *
			FROM mail_queue
			WHERE id = :id AND status = \'processing\' AND locked_by = :worker_id
			FOR UPDATE
		');
		$statement->execute(['id' => $jobId, 'worker_id' => substr($workerId, 0, 64)]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/** @brief Updates the cycle only after the corresponding SMTP transaction succeeded. */
	private function RecordReminderSent(PDO $connection, array $job, string $now): void
	{
		$cycleId = (int)$job['check_cycle_id'];
		$reminderNumber = (int)$job['reminder_number'];
		$update = $connection->prepare('
			UPDATE check_cycles
			SET reminders_sent = :reminder_number, last_reminder_sent_at = :sent_at, updated_at = :updated_at
			WHERE id = :id
				AND status = \'awaiting\'
				AND reminders_sent = :previous_number
		');
		$update->execute([
			'reminder_number' => $reminderNumber,
			'sent_at' => $now,
			'updated_at' => $now,
			'id' => $cycleId,
			'previous_number' => max(0, $reminderNumber - 1),
		]);

		if ($update->rowCount() !== 1)
		{
			return;
		}

		$audit = $connection->prepare('
			INSERT INTO audit_log
			(user_id, event_type, entity_type, entity_id, message, context_json, created_at)
			VALUES
			(:user_id, \'mail.reminder_sent\', \'monitor\', :monitor_id, \'mail.reminder_sent\', :context_json, :created_at)
		');
		$audit->execute([
			'user_id' => (int)$job['user_id'],
			'monitor_id' => (int)$job['monitor_id'],
			'context_json' => json_encode([
				'cycle_id' => $cycleId,
				'queue_id' => (int)$job['id'],
				'reminder_number' => $reminderNumber,
			], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
			'created_at' => $now,
		]);
	}

	/** @brief Records successful delivery of the initial due notice. */
	private function RecordDueNoticeSent(PDO $connection, array $job, string $now): void
	{
		$cycleId = (int)$job['check_cycle_id'];
		$update = $connection->prepare('
			UPDATE check_cycles
			SET due_notice_sent_at = :sent_at, updated_at = :updated_at
			WHERE id = :id
				AND status = \'awaiting\'
				AND due_notice_sent_at IS NULL
		');
		$update->execute([
			'sent_at' => $now,
			'updated_at' => $now,
			'id' => $cycleId,
		]);

		if ($update->rowCount() !== 1)
		{
			return;
		}

		$audit = $connection->prepare('
			INSERT INTO audit_log
			(user_id, event_type, entity_type, entity_id, message, context_json, created_at)
			VALUES
			(:user_id, \'mail.due_notice_sent\', \'monitor\', :monitor_id, \'mail.due_notice_sent\', :context_json, :created_at)
		');
		$audit->execute([
			'user_id' => (int)$job['user_id'],
			'monitor_id' => (int)$job['monitor_id'],
			'context_json' => json_encode([
				'cycle_id' => $cycleId,
				'queue_id' => (int)$job['id'],
			], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
			'created_at' => $now,
		]);
	}

	/** @brief Records a delivered safety invitation or reminder. */
	private function RecordSafetyMailSent(PDO $connection, array $job, string $now): void
	{
		$requestId = (int)$job['safety_request_id'];
		$mailType = (string)$job['mail_type'];

		if ($mailType === 'safety_invitation')
		{
			$update = $connection->prepare('
				UPDATE safety_contact_requests
				SET invitation_sent_at = :sent_at, updated_at = :updated_at
				WHERE id = :id AND status = \'pending\' AND invitation_sent_at IS NULL
			');
			$update->execute(['sent_at' => $now, 'updated_at' => $now, 'id' => $requestId]);

			if ($update->rowCount() === 1)
			{
				$remaining = $connection->prepare('
					SELECT COUNT(*) FROM safety_contact_requests
					WHERE check_cycle_id = :cycle_id AND invitation_sent_at IS NULL
				');
				$remaining->execute(['cycle_id' => (int)$job['check_cycle_id']]);

				if ((int)$remaining->fetchColumn() === 0)
				{
					$startGate = $connection->prepare('
						UPDATE check_cycles
						SET safety_gate_started_at = COALESCE(safety_gate_started_at, :started_at),
							safety_gate_deadline_at = COALESCE
							(
								safety_gate_deadline_at,
								TIMESTAMPADD
								(
									DAY,
									safety_response_window_days + (safety_reminder_interval_days * safety_max_reminders),
									:deadline_start
								)
							),
							updated_at = :updated_at
						WHERE id = :id AND status = \'safety_pending\'
					');
					$startGate->execute([
						'started_at' => $now,
						'deadline_start' => $now,
						'updated_at' => $now,
						'id' => (int)$job['check_cycle_id'],
					]);
				}
			}
		}
		else
		{
			$number = (int)$job['reminder_number'];
			$update = $connection->prepare('
				UPDATE safety_contact_requests
				SET reminders_sent = :number, last_reminder_sent_at = :sent_at, updated_at = :updated_at
				WHERE id = :id AND status = \'pending\' AND reminders_sent = :previous
			');
			$update->execute([
				'number' => $number,
				'sent_at' => $now,
				'updated_at' => $now,
				'id' => $requestId,
				'previous' => max(0, $number - 1),
			]);
		}

		$audit = $connection->prepare('
			INSERT INTO audit_log
			(user_id, event_type, entity_type, entity_id, message, context_json, created_at)
			VALUES (:user_id, :event_type, \'monitor\', :monitor_id, :message, :context_json, :created_at)
		');
		$eventType = $mailType === 'safety_invitation' ? 'mail.safety_invitation_sent' : 'mail.safety_reminder_sent';
		$audit->execute([
			'user_id' => (int)$job['user_id'],
			'event_type' => $eventType,
			'monitor_id' => (int)$job['monitor_id'],
			'message' => $eventType,
			'context_json' => json_encode([
				'cycle_id' => (int)$job['check_cycle_id'],
				'safety_request_id' => $requestId,
				'reminder_number' => $job['reminder_number'],
			], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
			'created_at' => $now,
		]);
	}

	/** @brief Records successful recipient delivery and honestly starts escalation on the first success. */
	private function RecordRecipientNotificationSent(PDO $connection, array $job, string $now): void
	{
		$deliveryId = (int)$job['recipient_delivery_id'];
		$update = $connection->prepare('
			UPDATE recipient_release_deliveries
			SET status = \'sent\', sent_at = :sent_at, failed_at = NULL, cancelled_at = NULL,
				portal_expires_at = CASE
					WHEN portal_released_at IS NULL AND portal_availability_days IS NOT NULL
					THEN TIMESTAMPADD(DAY, portal_availability_days, :portal_expiry_base)
					ELSE portal_expires_at
				END,
				portal_released_at = COALESCE(portal_released_at, :portal_released_at),
				last_error = NULL, updated_at = :updated_at
			WHERE id = :id AND status IN (\'queued\', \'cancelled\')
		');
		$update->execute([
			'sent_at' => $now,
			'portal_released_at' => $now,
			'portal_expiry_base' => $now,
			'updated_at' => $now,
			'id' => $deliveryId,
		]);

		if ($update->rowCount() !== 1)
		{
			return;
		}

		$releaseStatement = $connection->prepare('
			SELECT release_id FROM recipient_release_deliveries WHERE id = :id
		');
		$releaseStatement->execute(['id' => $deliveryId]);
		$releaseId = (int)$releaseStatement->fetchColumn();
		$counts = $connection->prepare('
			SELECT COUNT(*) AS total, SUM(status = \'sent\') AS sent_total
			FROM recipient_release_deliveries WHERE release_id = :release_id
		');
		$counts->execute(['release_id' => $releaseId]);
		$row = $counts->fetch(PDO::FETCH_ASSOC);
		$total = is_array($row) ? (int)$row['total'] : 0;
		$sent = is_array($row) ? (int)$row['sent_total'] : 0;
		$status = $total > 0 && $sent >= $total ? 'sent' : 'partial';
		$updateRelease = $connection->prepare('
			UPDATE recipient_releases
			SET status = :status, first_sent_at = COALESCE(first_sent_at, :first_sent_at),
				completed_at = :completed_at, updated_at = :updated_at
			WHERE id = :id
		');
		$updateRelease->execute([
			'status' => $status,
			'first_sent_at' => $now,
			'completed_at' => $status === 'sent' ? $now : null,
			'updated_at' => $now,
			'id' => $releaseId,
		]);
		$escalate = $connection->prepare('
			UPDATE check_cycles
			SET status = \'escalated\', escalated_at = :escalated_at, updated_at = :updated_at
			WHERE id = :id AND status = \'overdue\'
		');
		$escalate->execute([
			'escalated_at' => $now,
			'updated_at' => $now,
			'id' => (int)$job['check_cycle_id'],
		]);

		if ($escalate->rowCount() === 1)
		{
			$this->InsertAudit($connection, $job, 'monitor.escalated', $now, ['release_id' => $releaseId]);
		}

		$this->InsertAudit($connection, $job, 'mail.recipient_sent', $now, [
			'release_id' => $releaseId,
			'delivery_id' => $deliveryId,
			'contact_id' => $job['contact_id'],
		]);
	}


	/** @brief Records successful delivery of a short-lived recipient access code. */
	private function RecordRecipientAccessCodeSent(PDO $connection, array $job, string $now): void
	{
		$update = $connection->prepare('
			UPDATE recipient_portal_codes
			SET sent_at = :sent_at, updated_at = :updated_at
			WHERE id = :id AND used_at IS NULL AND invalidated_at IS NULL
		');
		$update->execute([
			'sent_at' => $now,
			'updated_at' => $now,
			'id' => (int)$job['recipient_portal_code_id'],
		]);
		$this->InsertAudit($connection, $job, 'recipient.portal_code_sent', $now, [
			'delivery_id' => $job['recipient_delivery_id'],
		]);
	}

	/** @brief Mirrors a final queue failure into its safety or recipient delivery state. */
	private function RecordLinkedFinalFailure(PDO $connection, array $job, string $error, string $now): void
	{
		$mailType = (string)$job['mail_type'];

		if (in_array($mailType, ['safety_invitation', 'safety_reminder'], true))
		{
			$this->InsertAudit($connection, $job, 'mail.safety_failed', $now, [
				'safety_request_id' => $job['safety_request_id'],
				'mail_type' => $mailType,
			]);
			return;
		}

		if ($mailType === 'recipient_access_code')
		{
			$update = $connection->prepare('
				UPDATE recipient_portal_codes
				SET invalidated_at = COALESCE(invalidated_at, :invalidated_at), updated_at = :updated_at
				WHERE id = :id AND used_at IS NULL
			');
			$update->execute([
				'invalidated_at' => $now,
				'updated_at' => $now,
				'id' => (int)$job['recipient_portal_code_id'],
			]);
			$redact = $connection->prepare('UPDATE mail_queue SET body_text = \'[Access code redacted after final failure]\' WHERE id = :id');
			$redact->execute(['id' => (int)$job['id']]);
			$this->InsertAudit($connection, $job, 'recipient.portal_code_failed', $now, [
				'delivery_id' => $job['recipient_delivery_id'],
			]);
			return;
		}

		if ($mailType !== 'recipient_notification')
		{
			return;
		}

		$deliveryId = (int)$job['recipient_delivery_id'];
		$update = $connection->prepare('
			UPDATE recipient_release_deliveries
			SET status = \'failed\', last_error = :last_error, failed_at = :failed_at, updated_at = :updated_at
			WHERE id = :id AND status = \'queued\'
		');
		$update->execute([
			'last_error' => $error,
			'failed_at' => $now,
			'updated_at' => $now,
			'id' => $deliveryId,
		]);
		$release = $connection->prepare('
			SELECT rr.id, rr.first_sent_at
			FROM recipient_releases rr
			INNER JOIN recipient_release_deliveries rrd ON rrd.release_id = rr.id
			WHERE rrd.id = :delivery_id
		');
		$release->execute(['delivery_id' => $deliveryId]);
		$row = $release->fetch(PDO::FETCH_ASSOC);

		if (is_array($row))
		{
			$releaseStatus = empty($row['first_sent_at']) ? 'failed' : 'partial';
			$updateRelease = $connection->prepare('
				UPDATE recipient_releases SET status = :status, updated_at = :updated_at WHERE id = :id
			');
			$updateRelease->execute(['status' => $releaseStatus, 'updated_at' => $now, 'id' => (int)$row['id']]);
		}

		$this->InsertAudit($connection, $job, 'mail.recipient_failed', $now, [
			'delivery_id' => $deliveryId,
			'contact_id' => $job['contact_id'],
		]);
	}

	/** @brief Inserts a content-free mail/lifecycle audit entry. */
	private function InsertAudit(PDO $connection, array $job, string $eventType, string $createdAt, array $context): void
	{
		$statement = $connection->prepare('
			INSERT INTO audit_log
			(user_id, event_type, entity_type, entity_id, message, context_json, created_at)
			VALUES (:user_id, :event_type, \'monitor\', :monitor_id, :message, :context_json, :created_at)
		');
		$statement->execute([
			'user_id' => (int)$job['user_id'],
			'event_type' => $eventType,
			'monitor_id' => (int)$job['monitor_id'],
			'message' => $eventType,
			'context_json' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
			'created_at' => $createdAt,
		]);
	}

	/** @brief Appends a content-free delivery attempt record. */
	private function InsertLog(PDO $connection, array $job, string $status, ?string $error, string $createdAt): void
	{
		$statement = $connection->prepare('
			INSERT INTO mail_log
			(queue_id, user_id, check_cycle_id, mail_type, recipient_email, subject, attempt_number, status, error_message, smtp_message, created_at)
			VALUES
			(:queue_id, :user_id, :check_cycle_id, :mail_type, :recipient_email, :subject, :attempt_number, :status, :error_message, NULL, :created_at)
		');
		$statement->execute([
			'queue_id' => (int)$job['id'],
			'user_id' => (int)$job['user_id'],
			'check_cycle_id' => $job['check_cycle_id'],
			'mail_type' => (string)$job['mail_type'],
			'recipient_email' => (string)$job['recipient_email'],
			'subject' => (string)$job['subject'],
			'attempt_number' => (int)$job['attempt_count'],
			'status' => $status,
			'error_message' => $error,
			'created_at' => $createdAt,
		]);
	}

	/** @brief Returns current UTC database time. */
	private function Now(): string
	{
		return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
	}

	/** @brief Returns a UTC timestamp after a non-negative delay. */
	private function AddSeconds(int $seconds): string
	{
		return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
			->modify('+' . max(0, $seconds) . ' seconds')
			->format('Y-m-d H:i:s');
	}
}
