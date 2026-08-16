<?php

/**
 * @file EscalationService.php
 * @brief Optional safety-contact gate and immutable recipient-mail release staging.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Pulse\Core\Database;
use Pulse\Core\EmailAddressCollection;
use Pulse\Core\Logger;
use RuntimeException;
use Throwable;

/**
 * @brief Advances escalation only through explicit, audited, fail-closed stages.
 */
final class EscalationService
{
	private Database $_database;
	private MonitorStateMachine $_stateMachine;
	private NotificationComposer $_composer;
	private Logger $_logger;
	private int $_maxAttempts;

	/** @brief Constructs the escalation service. */
	public function __construct(
		Database $database,
		MonitorStateMachine $stateMachine,
		NotificationComposer $composer,
		Logger $logger,
		int $maxAttempts
	)
	{
		$this->_database = $database;
		$this->_stateMachine = $stateMachine;
		$this->_composer = $composer;
		$this->_logger = $logger;
		$this->_maxAttempts = $maxAttempts;
	}

	/**
	 * @brief Starts a safety gate and transactionally queues every initial request.
	 * @return int Number of safety-contact requests created, or zero when configuration blocks the gate.
	 */
	public function StartSafetyGate(int $cycleId): int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$cycle = $this->LockCycle($connection, $cycleId);

			if (!is_array($cycle) || (string)$cycle['status'] !== MonitorStateMachine::AWAITING)
			{
				$connection->commit();
				return 0;
			}

			if ((string)$cycle['escalation_policy_snapshot'] !== 'safety_contact')
			{
				$connection->commit();
				return 0;
			}

			$contacts = $this->FindSafetyContacts($connection, (int)$cycle['monitor_id']);
			$required = (int)$cycle['safety_required_confirmations'];

			if ($contacts === [] || $required < 1 || $required > count($contacts))
			{
				$this->WriteAudit($connection, $cycle, 'monitor.safety_blocked', ['reason' => 'invalid_configuration']);
				$connection->commit();
				return 0;
			}

			foreach ($contacts as $contact)
			{
				if (!EmailAddressCollection::HasChecked($contact))
				{
					$this->WriteAudit($connection, $cycle, 'monitor.safety_blocked', ['reason' => 'unchecked_safety_contact']);
					$connection->commit();
					return 0;
				}
			}

			$this->_stateMachine->AssertTransition(MonitorStateMachine::AWAITING, MonitorStateMachine::SAFETY_PENDING);
			$now = $this->Now();
			$update = $connection->prepare('
				UPDATE check_cycles
				SET status = :status, updated_at = :updated_at
				WHERE id = :id
			');
			$update->execute([
				'status' => MonitorStateMachine::SAFETY_PENDING,
				'updated_at' => $now,
				'id' => $cycleId,
			]);

			foreach ($contacts as $contact)
			{
				$emails = EmailAddressCollection::Checked($contact);
				$rawToken = bin2hex(random_bytes(32));
				$expiresAt = $this->SafetyTokenExpiry($cycle);
				$insertRequest = $connection->prepare('
					INSERT INTO safety_contact_requests
					(
						check_cycle_id, monitor_id, contact_id, contact_name, contact_email,
						notification_locale, invitation_subject, invitation_body, reminder_subject, reminder_body, status, created_at, updated_at
					)
					VALUES
					(
						:check_cycle_id, :monitor_id, :contact_id, :contact_name, :contact_email,
						:notification_locale, :invitation_subject, :invitation_body, :reminder_subject, :reminder_body, \'pending\', :created_at, :updated_at
					)
				');
				$insertRequest->execute([
					'check_cycle_id' => $cycleId,
					'monitor_id' => (int)$cycle['monitor_id'],
					'contact_id' => (int)$contact['id'],
					'contact_name' => (string)$contact['name'],
					'contact_email' => $emails[0],
					'notification_locale' => (string)$contact['notification_locale'],
					'invitation_subject' => $contact['safety_invitation_subject'] ?? null,
					'invitation_body' => $contact['safety_invitation_body'] ?? null,
					'reminder_subject' => $contact['safety_reminder_subject'] ?? null,
					'reminder_body' => $contact['safety_reminder_body'] ?? null,
					'created_at' => $now,
					'updated_at' => $now,
				]);
				$requestId = (int)$connection->lastInsertId();
				$this->InsertSafetyRequestEmails($connection, $requestId, $emails);
				$this->InsertSafetyToken($connection, $requestId, $rawToken, $expiresAt);
				$content = $this->_composer->ComposeSafetyInvitation([
					'contact_name' => (string)$contact['name'],
					'notification_locale' => (string)$contact['notification_locale'],
					'owner_name' => (string)$cycle['owner_name'],
					'monitor_name' => (string)$cycle['monitor_name'],
					'message_subject' => (string)($contact['safety_invitation_subject'] ?? ''),
					'message_body' => (string)($contact['safety_invitation_body'] ?? ''),
				], $rawToken);
				foreach ($emails as $index => $email)
				{
					$key = 'safety-invitation:' . $cycleId . ':' . (int)$contact['id'];
					$this->InsertQueue($connection, [
						'user_id' => (int)$cycle['user_id'],
						'check_cycle_id' => $cycleId,
						'monitor_id' => (int)$cycle['monitor_id'],
						'contact_id' => (int)$contact['id'],
						'safety_request_id' => $requestId,
						'recipient_delivery_id' => null,
						'mail_type' => 'safety_invitation',
						'idempotency_key' => $index === 0 ? $key : $key . ':address' . ($index + 1),
						'reminder_number' => null,
						'recipient_email' => $email,
						'subject' => $content['subject'],
						'body_text' => $content['body_text'],
						'available_at' => $now,
					]);
				}
			}

			$this->WriteAudit($connection, $cycle, 'monitor.safety_requested', [
				'contact_count' => count($contacts),
				'required_confirmations' => $required,
			]);
			$connection->commit();
			$this->_logger->Info('Safety-contact gate started', ['cycle_id' => $cycleId, 'contact_count' => count($contacts)]);

			return count($contacts);
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Queues due safety reminders and expires fully delivered unanswered gates.
	 * @return array{reminders_ready: int, expired: int}
	 */
	public function RunSafetyGates(): array
	{
		$remindersReady = 0;
		$expired = 0;
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$cycles = $this->_database->GetConnection()->query('
			SELECT cc.*, m.user_id, m.name AS monitor_name, u.display_name AS owner_name
			FROM check_cycles cc
			INNER JOIN monitors m ON m.id = cc.monitor_id
			INNER JOIN users u ON u.id = m.user_id
			WHERE cc.status = \'safety_pending\' AND m.is_paused = 0 AND m.is_archived = 0 AND u.is_active = 1
			ORDER BY cc.id ASC
		')->fetchAll(PDO::FETCH_ASSOC);

		foreach (is_array($cycles) ? $cycles : [] as $cycle)
		{
			if (empty($cycle['safety_gate_started_at']) || empty($cycle['safety_gate_deadline_at']))
			{
				continue;
			}

			$requests = $this->FindSafetyRequests((int)$cycle['id']);

			foreach ($requests as $request)
			{
				if ((string)$request['status'] !== 'pending')
				{
					continue;
				}

				$sent = (int)$request['reminders_sent'];
				$maximum = (int)$cycle['safety_max_reminders'];

				if ($sent >= $maximum)
				{
					continue;
				}

				$number = $sent + 1;
				$scheduledAt = $this->SafetyReminderTime($cycle, $number);

				if ($scheduledAt <= $now && $this->QueueSafetyReminder($cycle, $request, $number))
				{
					$remindersReady++;
				}
			}

			$deadline = new DateTimeImmutable((string)$cycle['safety_gate_deadline_at'], new DateTimeZone('UTC'));

			if ($deadline <= $now && $this->ExpireSafetyGate((int)$cycle['id']))
			{
				$expired++;
			}
		}

		return ['reminders_ready' => $remindersReady, 'expired' => $expired];
	}

	/**
	 * @brief Stages immutable recipient messages and queue jobs for one overdue cycle.
	 * @return array{status: string, queued: int, release_id: int, issues?: array<int, array{reason: string, recipient_name: string}>}
	 */
	public function StageRecipientRelease(int $cycleId): array
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$cycle = $this->LockCycle($connection, $cycleId);

			if (!is_array($cycle) || !in_array((string)$cycle['status'], [MonitorStateMachine::OVERDUE, MonitorStateMachine::ESCALATED], true))
			{
				$connection->commit();
				return ['status' => 'unavailable', 'queued' => 0, 'release_id' => 0, 'issues' => []];
			}

			$existing = $connection->prepare('SELECT * FROM recipient_releases WHERE check_cycle_id = :cycle_id FOR UPDATE');
			$existing->execute(['cycle_id' => $cycleId]);
			$release = $existing->fetch(PDO::FETCH_ASSOC);

			if (is_array($release) && (string)$release['status'] !== 'blocked')
			{
				$count = $connection->prepare('SELECT COUNT(*) FROM recipient_release_deliveries WHERE release_id = :release_id');
				$count->execute(['release_id' => (int)$release['id']]);
				$connection->commit();
				return ['status' => (string)$release['status'], 'queued' => (int)$count->fetchColumn(), 'release_id' => (int)$release['id'], 'issues' => []];
			}

			$recipients = $this->FindReleaseRecipients($connection, (int)$cycle['monitor_id']);
			$blockedIssues = $this->ReleaseBlockedIssues($recipients);
			$blockedReason = $blockedIssues[0]['reason'] ?? null;

			if ($blockedReason !== null)
			{
				if (is_array($release) && (string)$release['blocked_reason'] === $blockedReason)
				{
					$connection->commit();
					return ['status' => 'blocked', 'queued' => 0, 'release_id' => (int)$release['id'], 'issues' => $blockedIssues];
				}

				$releaseId = $this->UpsertBlockedRelease($connection, $cycle, $blockedReason);
				$this->WriteAudit($connection, $cycle, 'recipient.release_blocked', ['reason' => $blockedReason, 'issues' => $blockedIssues]);
				$connection->commit();
				return ['status' => 'blocked', 'queued' => 0, 'release_id' => $releaseId, 'issues' => $blockedIssues];
			}

			$now = $this->Now();

			if (is_array($release))
			{
				$releaseId = (int)$release['id'];
				$updateRelease = $connection->prepare('
					UPDATE recipient_releases
					SET status = \'pending\', blocked_reason = NULL, staged_at = :staged_at, updated_at = :updated_at
					WHERE id = :id
				');
				$updateRelease->execute(['staged_at' => $now, 'updated_at' => $now, 'id' => $releaseId]);
			}
			else
			{
				$insertRelease = $connection->prepare('
					INSERT INTO recipient_releases
					(check_cycle_id, monitor_id, user_id, status, created_at, staged_at, updated_at)
					VALUES (:check_cycle_id, :monitor_id, :user_id, \'pending\', :created_at, :staged_at, :updated_at)
				');
				$insertRelease->execute([
					'check_cycle_id' => $cycleId,
					'monitor_id' => (int)$cycle['monitor_id'],
					'user_id' => (int)$cycle['user_id'],
					'created_at' => $now,
					'staged_at' => $now,
					'updated_at' => $now,
				]);
				$releaseId = (int)$connection->lastInsertId();
			}

			$this->SnapshotRecipientLocations($connection, $releaseId, (int)$cycle['monitor_id']);
			$queuedNotifications = 0;

			foreach ($recipients as $recipient)
			{
				$emails = EmailAddressCollection::Checked($recipient);
				$rawPortalToken = bin2hex(random_bytes(32));
				$portalUrl = $this->_composer->RecipientPortalUrl($rawPortalToken, (string)$recipient['notification_locale']);
				$content = $this->_composer->ComposeRecipientNotification([
					'recipient_name' => (string)$recipient['name'],
					'notification_locale' => (string)$recipient['notification_locale'],
					'owner_name' => (string)$cycle['owner_name'],
					'monitor_name' => (string)$cycle['monitor_name'],
					'message_subject' => (string)$recipient['message_subject'],
					'message_body' => (string)$recipient['message_body'],
					'portal_url' => $portalUrl,
				]);
				$portalContent = $this->_composer->ComposeRecipientPortalContent([
					'recipient_name' => (string)$recipient['name'],
					'notification_locale' => (string)$recipient['notification_locale'],
					'owner_name' => (string)$cycle['owner_name'],
					'monitor_name' => (string)$cycle['monitor_name'],
					'portal_message_override_enabled' => !empty($recipient['portal_message_override_enabled']),
					'portal_message_override' => (string)($recipient['portal_message_override'] ?? ''),
					'portal_intro_text' => (string)($recipient['portal_intro_text'] ?? ''),
				]);
				$storedContent = $this->_composer->ComposeRecipientNotification([
					'recipient_name' => (string)$recipient['name'],
					'notification_locale' => (string)$recipient['notification_locale'],
					'owner_name' => (string)$cycle['owner_name'],
					'monitor_name' => (string)$cycle['monitor_name'],
					'message_subject' => (string)$recipient['message_subject'],
					'message_body' => (string)$recipient['message_body'],
					'portal_url' => '[Recipient portal link redacted]',
				]);
				$insertDelivery = $connection->prepare('
					INSERT INTO recipient_release_deliveries
					(
						release_id, check_cycle_id, monitor_id, contact_id, recipient_name,
						recipient_email, notification_locale, portal_token_hash, portal_availability_days,
						subject, body_text, portal_intro_text, portal_message_text, status, created_at, updated_at
					)
					VALUES
					(
						:release_id, :check_cycle_id, :monitor_id, :contact_id, :recipient_name,
						:recipient_email, :notification_locale, :portal_token_hash, :portal_availability_days,
						:subject, :body_text, :portal_intro_text, :portal_message_text, \'queued\', :created_at, :updated_at
					)
				');
				$insertDelivery->execute([
					'release_id' => $releaseId,
					'check_cycle_id' => $cycleId,
					'monitor_id' => (int)$cycle['monitor_id'],
					'contact_id' => (int)$recipient['contact_id'],
					'recipient_name' => (string)$recipient['name'],
					'recipient_email' => $emails[0],
					'notification_locale' => (string)$recipient['notification_locale'],
					'portal_token_hash' => hash('sha256', $rawPortalToken),
					'portal_availability_days' => $recipient['portal_availability_days'],
					'subject' => $storedContent['subject'],
					'body_text' => $storedContent['body_text'],
					'portal_intro_text' => $portalContent['intro_text'],
					'portal_message_text' => $portalContent['message_text'],
					'created_at' => $now,
					'updated_at' => $now,
				]);
				$deliveryId = (int)$connection->lastInsertId();
				$this->InsertRecipientDeliveryEmails($connection, $deliveryId, $emails);
				$this->SnapshotRecipientDocuments($connection, $deliveryId, (int)$recipient['monitor_contact_id']);
				$queueId = 0;

				foreach ($emails as $index => $email)
				{
					$key = 'recipient-notification:' . $cycleId . ':' . (int)$recipient['contact_id'];
					$currentQueueId = $this->InsertQueue($connection, [
						'user_id' => (int)$cycle['user_id'],
						'check_cycle_id' => $cycleId,
						'monitor_id' => (int)$cycle['monitor_id'],
						'contact_id' => (int)$recipient['contact_id'],
						'safety_request_id' => null,
						'recipient_delivery_id' => $deliveryId,
						'recipient_portal_code_id' => null,
						'mail_type' => 'recipient_notification',
						'idempotency_key' => $index === 0 ? $key : $key . ':address' . ($index + 1),
						'reminder_number' => null,
						'recipient_email' => $email,
						'subject' => $content['subject'],
						'body_text' => $content['body_text'],
						'available_at' => $now,
					]);
					$queueId = $queueId > 0 ? $queueId : $currentQueueId;
					$queuedNotifications++;
				}

				$updateDelivery = $connection->prepare('UPDATE recipient_release_deliveries SET queue_id = :queue_id WHERE id = :id');
				$updateDelivery->execute(['queue_id' => $queueId, 'id' => $deliveryId]);
			}


			$this->WriteAudit($connection, $cycle, 'recipient.release_staged', [
				'release_id' => $releaseId,
				'recipient_count' => count($recipients),
			]);
			$connection->commit();
			$this->_logger->Info('Recipient release staged', ['cycle_id' => $cycleId, 'recipient_count' => count($recipients)]);

			return ['status' => 'pending', 'queued' => $queuedNotifications, 'release_id' => $releaseId, 'issues' => []];
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @return array<string, mixed>|null @brief Resolves a current safety request without consuming its token. */
	public function FindSafetyRequestByToken(string $rawToken): ?array
	{
		if (!$this->IsTokenShapeValid($rawToken))
		{
			return null;
		}

		$statement = $this->_database->GetConnection()->prepare('
			SELECT scr.*, m.name AS monitor_name, u.display_name AS owner_name
			FROM safety_contact_requests scr
			INNER JOIN check_cycles cc ON cc.id = scr.check_cycle_id
			INNER JOIN monitors m ON m.id = scr.monitor_id
			INNER JOIN users u ON u.id = m.user_id
			INNER JOIN safety_request_tokens srt ON srt.safety_request_id = scr.id
			WHERE srt.token_hash = :token_hash
				AND scr.status = \'pending\'
				AND srt.expires_at >= UTC_TIMESTAMP()
				AND cc.status = \'safety_pending\'
			LIMIT 1
		');
		$statement->execute(['token_hash' => hash('sha256', $rawToken)]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Returns snapshotted language metadata for a safety token even after it becomes inactive.
	 * @return array{notification_locale: string}|null
	 */
	public function FindSafetyLanguageMetadata(string $rawToken): ?array
	{
		if (!$this->IsTokenShapeValid($rawToken))
		{
			return null;
		}

		$statement = $this->_database->GetConnection()->prepare('
			SELECT scr.notification_locale
			FROM safety_request_tokens srt
			INNER JOIN safety_contact_requests scr ON scr.id = srt.safety_request_id
			WHERE srt.token_hash = :token_hash
			LIMIT 1
		');
		$statement->execute(['token_hash' => hash('sha256', $rawToken)]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Records a deliberate safety-contact response and optionally postpones the monitor.
	 * @return string invalid, confirmed_waiting, confirmed_postponed, or declined.
	 */
	public function RespondToSafetyToken(string $rawToken, string $decision): string
	{
		if (!$this->IsTokenShapeValid($rawToken) || !in_array($decision, ['confirm', 'decline'], true))
		{
			return 'invalid';
		}

		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$statement = $connection->prepare('
				SELECT scr.*, cc.status AS cycle_status, cc.safety_required_confirmations,
					cc.safety_confirmation_days, m.user_id, m.name AS monitor_name,
					m.check_interval_days, m.response_window_days, m.reminder_interval_days,
					m.max_reminders, m.escalation_policy, m.safety_response_window_days,
					m.safety_reminder_interval_days, m.safety_max_reminders,
					m.safety_required_confirmations AS monitor_required_confirmations,
					m.safety_confirmation_days AS monitor_confirmation_days
				FROM safety_contact_requests scr
				INNER JOIN safety_request_tokens srt ON srt.safety_request_id = scr.id
				INNER JOIN check_cycles cc ON cc.id = scr.check_cycle_id
				INNER JOIN monitors m ON m.id = scr.monitor_id
				WHERE srt.token_hash = :token_hash
					AND scr.status = \'pending\'
					AND srt.expires_at >= UTC_TIMESTAMP()
				FOR UPDATE
			');
			$statement->execute(['token_hash' => hash('sha256', $rawToken)]);
			$request = $statement->fetch(PDO::FETCH_ASSOC);

			if (!is_array($request) || (string)$request['cycle_status'] !== MonitorStateMachine::SAFETY_PENDING)
			{
				$connection->commit();
				return 'invalid';
			}

			$now = $this->Now();

			if ($decision === 'decline')
			{
				$update = $connection->prepare('
					UPDATE safety_contact_requests
					SET status = \'declined\', declined_at = :responded_at, updated_at = :updated_at
					WHERE id = :id
				');
				$update->execute(['responded_at' => $now, 'updated_at' => $now, 'id' => (int)$request['id']]);
				$this->WriteAudit($connection, $request, 'safety_contact.cannot_confirm', ['contact_id' => $request['contact_id']]);
				$connection->commit();
				return 'declined';
			}

			$update = $connection->prepare('
				UPDATE safety_contact_requests
				SET status = \'confirmed\', confirmed_at = :responded_at, updated_at = :updated_at
				WHERE id = :id
			');
			$update->execute(['responded_at' => $now, 'updated_at' => $now, 'id' => (int)$request['id']]);
			$count = $connection->prepare('
				SELECT COUNT(*) FROM safety_contact_requests
				WHERE check_cycle_id = :cycle_id AND status = \'confirmed\'
			');
			$count->execute(['cycle_id' => (int)$request['check_cycle_id']]);
			$confirmations = (int)$count->fetchColumn();
			$updateCount = $connection->prepare('
				UPDATE check_cycles SET safety_confirmation_count = :count, updated_at = :updated_at WHERE id = :id
			');
			$updateCount->execute(['count' => $confirmations, 'updated_at' => $now, 'id' => (int)$request['check_cycle_id']]);
			$this->WriteAudit($connection, $request, 'safety_contact.confirmed', ['contact_id' => $request['contact_id'], 'confirmation_count' => $confirmations]);

			if ($confirmations < (int)$request['safety_required_confirmations'])
			{
				$connection->commit();
				return 'confirmed_waiting';
			}

			$this->PostponeCycle($connection, $request, $now, $confirmations);
			$connection->commit();
			return 'confirmed_postponed';
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/**
	 * @brief Moves one owned active safety-contact deadline into the past for timeout testing.
	 * @return bool True when a current safety-contact gate was adjusted.
	 */
	public function ExpireDebugSafetyWindowForUser(int $monitorId, int $userId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$statement = $connection->prepare('
				SELECT cc.*, m.user_id, m.name AS monitor_name
				FROM check_cycles cc
				INNER JOIN monitors m ON m.id = cc.monitor_id
				WHERE cc.monitor_id = :monitor_id
					AND m.user_id = :user_id
					AND m.is_paused = 0
					AND m.is_archived = 0
					AND cc.status = \'safety_pending\'
					AND cc.safety_gate_started_at IS NOT NULL
					AND cc.safety_gate_deadline_at IS NOT NULL
				ORDER BY cc.id DESC
				LIMIT 1
				FOR UPDATE
			');
			$statement->execute(['monitor_id' => $monitorId, 'user_id' => $userId]);
			$cycle = $statement->fetch(PDO::FETCH_ASSOC);

			if (!is_array($cycle))
			{
				$connection->commit();
				return false;
			}

			$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
			$deadline = $now->modify('-1 second')->format('Y-m-d H:i:s');
			$updatedAt = $now->format('Y-m-d H:i:s');
			$update = $connection->prepare('
				UPDATE check_cycles
				SET safety_gate_deadline_at = :deadline, updated_at = :updated_at
				WHERE id = :id
			');
			$update->execute([
				'deadline' => $deadline,
				'updated_at' => $updatedAt,
				'id' => (int)$cycle['id'],
			]);
			$this->WriteAudit($connection, $cycle, 'monitor.debug_safety_window_expired', [
				'cycle_id' => (int)$cycle['id'],
			]);
			$connection->commit();
			return true;
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @brief Resolves the current awaiting safety-contact cycle for a development send action. */
	public function FindDebugSafetyGateCycleForUser(int $monitorId, int $userId): ?int
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT cc.id
			FROM check_cycles cc
			INNER JOIN monitors m ON m.id = cc.monitor_id
			WHERE cc.monitor_id = :monitor_id
				AND m.user_id = :user_id
				AND m.is_paused = 0
				AND m.is_archived = 0
				AND cc.status = \'awaiting\'
				AND cc.escalation_policy_snapshot = \'safety_contact\'
				AND cc.due_notice_sent_at IS NOT NULL
			ORDER BY cc.id DESC
			LIMIT 1
		');
		$statement->execute(['monitor_id' => $monitorId, 'user_id' => $userId]);
		$cycleId = $statement->fetchColumn();

		return $cycleId === false ? null : (int)$cycleId;
	}

	/**
	 * @brief Debug-only helper that advances an open cycle to Overdue before staging recipients.
	 * @return int|null Current cycle ID, or null when unavailable.
	 */
	public function PrepareDebugRecipientReleaseForUser(int $monitorId, int $userId): ?int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$statement = $connection->prepare('
				SELECT cc.*, m.user_id, m.name AS monitor_name
				FROM check_cycles cc
				INNER JOIN monitors m ON m.id = cc.monitor_id
				WHERE cc.monitor_id = :monitor_id AND m.user_id = :user_id
					AND cc.status IN (\'awaiting\', \'safety_pending\', \'overdue\')
				ORDER BY cc.id DESC LIMIT 1 FOR UPDATE
			');
			$statement->execute(['monitor_id' => $monitorId, 'user_id' => $userId]);
			$cycle = $statement->fetch(PDO::FETCH_ASSOC);

			if (!is_array($cycle))
			{
				$connection->commit();
				return null;
			}

			if ((string)$cycle['status'] !== MonitorStateMachine::OVERDUE)
			{
				$this->_stateMachine->AssertTransition((string)$cycle['status'], MonitorStateMachine::OVERDUE);
				$now = $this->Now();
				$update = $connection->prepare('
					UPDATE check_cycles SET status = \'overdue\', overdue_at = :overdue_at, updated_at = :updated_at WHERE id = :id
				');
				$update->execute(['overdue_at' => $now, 'updated_at' => $now, 'id' => (int)$cycle['id']]);
				$this->CancelCycleWork($connection, (int)$cycle['id'], $now);
				$this->WriteAudit($connection, $cycle, 'monitor.debug_recipient_release');
			}

			$connection->commit();
			return (int)$cycle['id'];
		}
		catch (Throwable $throwable)
		{
			$connection->rollBack();
			throw $throwable;
		}
	}

	/** @return array<int> @brief Returns queued initial safety-contact jobs for one active gate. */
	public function FindPendingQueueIdsForSafetyInvitations(int $cycleId): array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT id
			FROM mail_queue
			WHERE check_cycle_id = :cycle_id
				AND mail_type = \'safety_invitation\'
				AND status IN (\'queued\', \'retrying\')
			ORDER BY id ASC
		');
		$statement->execute(['cycle_id' => $cycleId]);
		$rows = $statement->fetchAll(PDO::FETCH_COLUMN);

		return is_array($rows) ? array_map('intval', $rows) : [];
	}

	/** @return array<int> @brief Returns queued recipient jobs for an immutable release. */
	public function FindPendingQueueIdsForRelease(int $releaseId): array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT mq.id
			FROM mail_queue mq
			INNER JOIN recipient_release_deliveries rrd ON rrd.id = mq.recipient_delivery_id
			WHERE rrd.release_id = :release_id AND mq.status IN (\'queued\', \'retrying\')
			  AND mq.mail_type = \'recipient_notification\'
			ORDER BY mq.id ASC
		');
		$statement->execute(['release_id' => $releaseId]);
		$rows = $statement->fetchAll(PDO::FETCH_COLUMN);

		return is_array($rows) ? array_map('intval', $rows) : [];
	}

	/** @return array<string, mixed>|null @brief Locks one cycle with monitor and owner configuration. */
	private function LockCycle(PDO $connection, int $cycleId): ?array
	{
		$statement = $connection->prepare('
			SELECT cc.*, m.user_id, m.name AS monitor_name, m.is_paused,
				m.safety_invitation_subject, m.safety_invitation_body,
				m.safety_reminder_subject, m.safety_reminder_body, u.display_name AS owner_name
			FROM check_cycles cc
			INNER JOIN monitors m ON m.id = cc.monitor_id
			INNER JOIN users u ON u.id = m.user_id
			WHERE cc.id = :id
			FOR UPDATE
		');
		$statement->execute(['id' => $cycleId]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}

	/** @return array<int, array<string, mixed>> @brief Returns configured safety contacts. */
	private function FindSafetyContacts(PDO $connection, int $monitorId): array
	{
		$statement = $connection->prepare('
			SELECT
				c.id, c.name, c.email, c.email_checked_at,
				c.email_2, c.email_2_checked_at, c.email_3, c.email_3_checked_at, c.email_4, c.email_4_checked_at,
				c.notification_locale,
				invitation.subject AS safety_invitation_subject, invitation.body_text AS safety_invitation_body,
				reminder.subject AS safety_reminder_subject, reminder.body_text AS safety_reminder_body
			FROM monitor_safety_contacts msc
			INNER JOIN contacts c ON c.id = msc.contact_id
			LEFT JOIN monitor_mail_templates invitation
				ON invitation.monitor_id = msc.monitor_id
				AND invitation.template_key = \'safety_invitation\'
				AND invitation.locale = c.notification_locale
			LEFT JOIN monitor_mail_templates reminder
				ON reminder.monitor_id = msc.monitor_id
				AND reminder.template_key = \'safety_reminder\'
				AND reminder.locale = c.notification_locale
			WHERE msc.monitor_id = :monitor_id
			ORDER BY msc.sort_order ASC, msc.id ASC
		');
		$statement->execute(['monitor_id' => $monitorId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/** @return array<int, array<string, mixed>> @brief Returns safety requests for one cycle. */
	private function FindSafetyRequests(int $cycleId): array
	{
		$statement = $this->_database->GetConnection()->prepare('
			SELECT scr.*
			FROM safety_contact_requests scr
			WHERE scr.check_cycle_id = :cycle_id
			ORDER BY scr.id ASC
		');
		$statement->execute(['cycle_id' => $cycleId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/** @brief Queues one safety reminder with an additional expiring token. */
	private function QueueSafetyReminder(array $cycle, array $request, int $number): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$key = 'safety-reminder:' . (int)$request['id'] . ':' . $number;
			$existing = $connection->prepare('SELECT id FROM mail_queue WHERE idempotency_key = :key LIMIT 1');
			$existing->execute(['key' => $key]);

			if ($existing->fetchColumn() !== false)
			{
				$connection->commit();
				return false;
			}

			$locked = $connection->prepare('
				SELECT * FROM safety_contact_requests
				WHERE id = :id AND status = \'pending\' AND reminders_sent = :previous
				FOR UPDATE
			');
			$locked->execute(['id' => (int)$request['id'], 'previous' => $number - 1]);
			$current = $locked->fetch(PDO::FETCH_ASSOC);

			if (!is_array($current))
			{
				$connection->commit();
				return false;
			}

			$rawToken = bin2hex(random_bytes(32));
			$this->InsertSafetyToken(
				$connection,
				(int)$request['id'],
				$rawToken,
				$this->SafetyTokenExpiry($cycle)
			);
			$content = $this->_composer->ComposeSafetyReminder([
				'contact_name' => (string)$request['contact_name'],
				'notification_locale' => (string)$request['notification_locale'],
				'owner_name' => (string)$cycle['owner_name'],
				'monitor_name' => (string)$cycle['monitor_name'],
				'safety_max_reminders' => (int)$cycle['safety_max_reminders'],
				'message_subject' => (string)($current['reminder_subject'] ?? ''),
				'message_body' => (string)($current['reminder_body'] ?? ''),
			], $rawToken, $number);
			$emails = $this->FindSafetyRequestEmails($connection, (int)$request['id'], (string)$request['contact_email']);

			foreach ($emails as $index => $email)
			{
				$this->InsertQueue($connection, [
					'user_id' => (int)$cycle['user_id'],
					'check_cycle_id' => (int)$cycle['id'],
					'monitor_id' => (int)$cycle['monitor_id'],
					'contact_id' => $request['contact_id'],
					'safety_request_id' => (int)$request['id'],
					'recipient_delivery_id' => null,
					'mail_type' => 'safety_reminder',
					'idempotency_key' => $index === 0 ? $key : $key . ':address' . ($index + 1),
					'reminder_number' => $number,
					'recipient_email' => $email,
					'subject' => $content['subject'],
					'body_text' => $content['body_text'],
					'available_at' => $this->Now(),
				]);
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

	/** @brief Expires a fully notified safety gate and moves it to Overdue. */
	private function ExpireSafetyGate(int $cycleId): bool
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();

		try
		{
			$cycle = $this->LockCycle($connection, $cycleId);

			if (!is_array($cycle) || (string)$cycle['status'] !== MonitorStateMachine::SAFETY_PENDING)
			{
				$connection->commit();
				return false;
			}

			if (empty($cycle['safety_gate_deadline_at']) || (string)$cycle['safety_gate_deadline_at'] > $this->Now())
			{
				$connection->commit();
				return false;
			}

			$blocked = $connection->prepare('
				SELECT COUNT(*)
				FROM safety_contact_requests
				WHERE check_cycle_id = :cycle_id
					AND
					(
						invitation_sent_at IS NULL
						OR (status = \'pending\' AND reminders_sent < :maximum)
					)
			');
			$blocked->execute([
				'cycle_id' => $cycleId,
				'maximum' => (int)$cycle['safety_max_reminders'],
			]);

			if ((int)$blocked->fetchColumn() > 0)
			{
				$connection->commit();
				return false;
			}

			$this->_stateMachine->AssertTransition(MonitorStateMachine::SAFETY_PENDING, MonitorStateMachine::OVERDUE);
			$now = $this->Now();
			$updateRequests = $connection->prepare('
				UPDATE safety_contact_requests
				SET status = \'expired\', updated_at = :updated_at
				WHERE check_cycle_id = :cycle_id AND status = \'pending\'
			');
			$updateRequests->execute(['updated_at' => $now, 'cycle_id' => $cycleId]);
			$updateCycle = $connection->prepare('
				UPDATE check_cycles
				SET status = \'overdue\', overdue_at = :overdue_at, updated_at = :updated_at
				WHERE id = :id
			');
			$updateCycle->execute(['overdue_at' => $now, 'updated_at' => $now, 'id' => $cycleId]);
			$this->WriteAudit($connection, $cycle, 'monitor.safety_expired', ['cycle_id' => $cycleId]);
			$this->WriteAudit($connection, $cycle, 'monitor.overdue', ['cycle_id' => $cycleId]);
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
	 * @brief Snapshots the documents assigned to one recipient at release staging time.
	 * @param PDO $connection Active release transaction.
	 * @param int $deliveryId Recipient delivery ID.
	 * @param int $monitorContactId Monitor-recipient assignment ID.
	 */
	private function SnapshotRecipientDocuments(PDO $connection, int $deliveryId, int $monitorContactId): void
	{
		$statement = $connection->prepare(<<<'SQL'
			INSERT INTO recipient_delivery_documents
			(
				recipient_delivery_id, source_document_id, title, description, storage_type,
				text_content, stored_filename, original_filename, mime_type, file_size_bytes, created_at
			)
			SELECT
				:delivery_id, d.id, d.title, d.description, d.storage_type,
				d.text_content, d.stored_filename, d.original_filename, d.mime_type, d.file_size_bytes, UTC_TIMESTAMP()
			FROM document_monitor_contacts dmc
			INNER JOIN documents d ON d.id = dmc.document_id
			WHERE dmc.monitor_contact_id = :monitor_contact_id
			ORDER BY d.created_at ASC, d.id ASC
		SQL);
		$statement->execute([
			'delivery_id' => $deliveryId,
			'monitor_contact_id' => $monitorContactId,
		]);
	}

	/**
	 * @brief Snapshots the configured bounded location trail once for an immutable recipient release.
	 * @param PDO $connection Active release transaction.
	 * @param int $releaseId Recipient release ID.
	 * @param int $monitorId Monitor ID.
	 */
	private function SnapshotRecipientLocations(PDO $connection, int $releaseId, int $monitorId): void
	{
		$clear = $connection->prepare('DELETE FROM recipient_release_locations WHERE release_id = :release_id');
		$clear->execute(['release_id' => $releaseId]);
		$settings = $connection->prepare('SELECT portal_location_sharing_enabled, portal_location_history_limit FROM monitors WHERE id = :id');
		$settings->execute(['id' => $monitorId]);
		$monitor = $settings->fetch(PDO::FETCH_ASSOC);

		if (!is_array($monitor) || empty($monitor['portal_location_sharing_enabled']))
		{
			return;
		}

		$limit = max(1, min(20, (int)$monitor['portal_location_history_limit']));
		$locations = $connection->prepare('
			SELECT latitude, longitude, accuracy_meters, address_label, created_at
			FROM check_in_locations
			WHERE monitor_id = :monitor_id
			ORDER BY created_at DESC, id DESC
			LIMIT ' . $limit . '
		');
		$locations->execute(['monitor_id' => $monitorId]);
		$rows = $locations->fetchAll(PDO::FETCH_ASSOC);

		if (!is_array($rows) || $rows === [])
		{
			return;
		}

		$insert = $connection->prepare('
			INSERT INTO recipient_release_locations
			(release_id, sequence_number, latitude, longitude, accuracy_meters, address_label, checked_in_at, created_at)
			VALUES (:release_id, :sequence_number, :latitude, :longitude, :accuracy_meters, :address_label, :checked_in_at, UTC_TIMESTAMP())
		');

		foreach (array_values(array_reverse($rows)) as $index => $location)
		{
			$insert->execute([
				'release_id' => $releaseId,
				'sequence_number' => $index + 1,
				'latitude' => $location['latitude'],
				'longitude' => $location['longitude'],
				'accuracy_meters' => $location['accuracy_meters'],
				'address_label' => $location['address_label'],
				'checked_in_at' => $location['created_at'],
			]);
		}
	}

	/** @return array<int, array<string, mixed>> @brief Resolves current recipient/message configuration. */
	private function FindReleaseRecipients(PDO $connection, int $monitorId): array
	{
		$statement = $connection->prepare('
			SELECT
				mc.id AS monitor_contact_id, mc.contact_id, c.name, c.email, c.email_checked_at,
				c.email_2, c.email_2_checked_at, c.email_3, c.email_3_checked_at, c.email_4, c.email_4_checked_at,
				c.notification_locale,
				m.recipient_portal_expiry_days AS portal_availability_days,
				COALESCE(cm.subject, mmt.subject) AS message_subject,
				COALESCE(cm.body_text, mmt.body_text) AS message_body,
				cpm.body_text AS portal_message_override,
				COALESCE(cpm.is_enabled, 0) AS portal_message_override_enabled,
				mpt.intro_text AS portal_intro_text
			FROM monitor_contacts mc
			INNER JOIN monitors m ON m.id = mc.monitor_id
			INNER JOIN contacts c ON c.id = mc.contact_id
			LEFT JOIN contact_messages cm ON cm.monitor_contact_id = mc.id
			LEFT JOIN monitor_mail_templates mmt
				ON mmt.monitor_id = m.id
				AND mmt.template_key = \'recipient_default\'
				AND mmt.locale = c.notification_locale
			LEFT JOIN contact_portal_messages cpm ON cpm.monitor_contact_id = mc.id
			LEFT JOIN monitor_portal_templates mpt
				ON mpt.monitor_id = m.id
				AND mpt.locale = c.notification_locale
			WHERE mc.monitor_id = :monitor_id
			ORDER BY mc.sort_order ASC, mc.id ASC
		');
		$statement->execute(['monitor_id' => $monitorId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @brief Returns every fail-closed recipient release configuration problem.
	 * @return array<int, array{reason: string, recipient_name: string}>
	 */
	private function ReleaseBlockedIssues(array $recipients): array
	{
		if ($recipients === [])
		{
			return [['reason' => 'no_recipients', 'recipient_name' => '']];
		}

		$issues = [];

		foreach ($recipients as $recipient)
		{
			$recipientName = trim((string)($recipient['name'] ?? ''));

			if (!EmailAddressCollection::HasChecked($recipient))
			{
				$issues[] = ['reason' => 'unchecked_recipient', 'recipient_name' => $recipientName];
			}

			$subject = (string)($recipient['message_subject'] ?? '');
			$body = (string)($recipient['message_body'] ?? '');

			foreach (RecipientMessageValidator::Validate($subject, $body) as $reason)
			{
				$issues[] = ['reason' => $reason, 'recipient_name' => $recipientName];
			}
		}

		return $issues;
	}

	/** @brief Inserts or updates a blocked release marker. */
	private function UpsertBlockedRelease(PDO $connection, array $cycle, string $reason): int
	{
		$statement = $connection->prepare('
			INSERT INTO recipient_releases
			(check_cycle_id, monitor_id, user_id, status, blocked_reason, created_at, updated_at)
			VALUES (:check_cycle_id, :monitor_id, :user_id, \'blocked\', :blocked_reason, UTC_TIMESTAMP(), UTC_TIMESTAMP())
			ON DUPLICATE KEY UPDATE status = \'blocked\', blocked_reason = VALUES(blocked_reason), updated_at = UTC_TIMESTAMP(), id = LAST_INSERT_ID(id)
		');
		$statement->execute([
			'check_cycle_id' => (int)$cycle['id'],
			'monitor_id' => (int)$cycle['monitor_id'],
			'user_id' => (int)$cycle['user_id'],
			'blocked_reason' => $reason,
		]);
		$lookup = $connection->prepare('SELECT id FROM recipient_releases WHERE check_cycle_id = :cycle_id');
		$lookup->execute(['cycle_id' => (int)$cycle['id']]);

		return (int)$lookup->fetchColumn();
	}

	/** @brief Completes an external confirmation without claiming an owner check-in. */
	private function PostponeCycle(PDO $connection, array $request, string $now, int $confirmations): void
	{
		$this->_stateMachine->AssertTransition(MonitorStateMachine::SAFETY_PENDING, MonitorStateMachine::CONFIRMED);
		$cycleId = (int)$request['check_cycle_id'];
		$monitorId = (int)$request['monitor_id'];
		$days = max(1, (int)$request['safety_confirmation_days']);
		$startedAt = new DateTimeImmutable($now, new DateTimeZone('UTC'));
		$dueAt = $startedAt->add(new DateInterval('P' . $days . 'D'));
		$responseDeadline = $dueAt->add(new DateInterval('P' . max(1, (int)$request['response_window_days']) . 'D'));
		$close = $connection->prepare('
			UPDATE check_cycles
			SET status = \'confirmed\', confirmed_at = :confirmed_at, safety_confirmed_at = :safety_confirmed_at,
				safety_confirmation_count = :confirmation_count, updated_at = :updated_at
			WHERE id = :id
		');
		$close->execute([
			'confirmed_at' => $now,
			'safety_confirmed_at' => $now,
			'confirmation_count' => $confirmations,
			'updated_at' => $now,
			'id' => $cycleId,
		]);
		$this->CancelCycleWork($connection, $cycleId, $now);
		$cancelRequests = $connection->prepare('
			UPDATE safety_contact_requests SET status = \'cancelled\', updated_at = :updated_at
			WHERE check_cycle_id = :cycle_id AND status = \'pending\'
		');
		$cancelRequests->execute(['updated_at' => $now, 'cycle_id' => $cycleId]);
		$insert = $connection->prepare('
			INSERT INTO check_cycles
			(
				monitor_id, status, started_at, due_at, response_deadline_at,
				reminder_interval_days, max_reminders, escalation_policy_snapshot,
				safety_response_window_days, safety_reminder_interval_days, safety_max_reminders,
				safety_required_confirmations, safety_confirmation_days, reminders_sent, updated_at
			)
			VALUES
			(
				:monitor_id, \'scheduled\', :started_at, :due_at, :response_deadline_at,
				:reminder_interval_days, :max_reminders, :escalation_policy_snapshot,
				:safety_response_window_days, :safety_reminder_interval_days, :safety_max_reminders,
				:safety_required_confirmations, :safety_confirmation_days, 0, :updated_at
			)
		');
		$insert->execute([
			'monitor_id' => $monitorId,
			'started_at' => $now,
			'due_at' => $dueAt->format('Y-m-d H:i:s'),
			'response_deadline_at' => $responseDeadline->format('Y-m-d H:i:s'),
			'reminder_interval_days' => (int)$request['reminder_interval_days'],
			'max_reminders' => (int)$request['max_reminders'],
			'escalation_policy_snapshot' => (string)$request['escalation_policy'],
			'safety_response_window_days' => (int)$request['safety_response_window_days'],
			'safety_reminder_interval_days' => (int)$request['safety_reminder_interval_days'],
			'safety_max_reminders' => (int)$request['safety_max_reminders'],
			'safety_required_confirmations' => (int)$request['monitor_required_confirmations'],
			'safety_confirmation_days' => max(1, (int)($request['monitor_confirmation_days'] ?? $request['check_interval_days'])),
			'updated_at' => $now,
		]);
		$updateMonitor = $connection->prepare('
			UPDATE monitors
			SET last_safety_confirmed_at = :confirmed_at, last_safety_contact_id = :contact_id,
				next_check_due_at = :next_due_at, updated_at = :updated_at
			WHERE id = :id
		');
		$updateMonitor->execute([
			'confirmed_at' => $now,
			'contact_id' => $request['contact_id'],
			'next_due_at' => $dueAt->format('Y-m-d H:i:s'),
			'updated_at' => $now,
			'id' => $monitorId,
		]);
		$this->WriteAudit($connection, $request, 'monitor.safety_confirmed', [
			'contact_id' => $request['contact_id'],
			'confirmation_count' => $confirmations,
			'next_due_at' => $dueAt->format('Y-m-d H:i:s'),
		]);
	}

	/** @brief Cancels queued work rendered obsolete by a closed or debug-advanced cycle. */
	private function CancelCycleWork(PDO $connection, int $cycleId, string $now): void
	{
		$cancel = $connection->prepare('
			UPDATE mail_queue
			SET status = \'cancelled\',
				body_text = CASE
					WHEN mail_type IN (\'safety_invitation\', \'safety_reminder\') THEN \'[Safety link redacted after cancellation]\'
					WHEN mail_type = \'recipient_notification\' THEN \'[Recipient portal link redacted after cancellation]\'
					WHEN mail_type = \'recipient_access_code\' THEN \'[Access code redacted after cancellation]\'
					ELSE body_text
				END,
				cancelled_at = :cancelled_at, updated_at = :updated_at
			WHERE check_cycle_id = :cycle_id
				AND status IN (\'queued\', \'retrying\', \'failed\')
		');
		$cancel->execute(['cancelled_at' => $now, 'updated_at' => $now, 'cycle_id' => $cycleId]);
	}

	/** @brief Inserts one immutable queue job inside the caller's transaction. @return int Queue ID. */
	private function InsertQueue(PDO $connection, array $message): int
	{
		$statement = $connection->prepare('
			INSERT INTO mail_queue
			(
				user_id, check_cycle_id, monitor_id, contact_id, safety_request_id, recipient_delivery_id, recipient_portal_code_id,
				mail_type, idempotency_key, reminder_number, recipient_email, subject, body_text,
				status, attempt_count, max_attempts, available_at, created_at, updated_at
			)
			VALUES
			(
				:user_id, :check_cycle_id, :monitor_id, :contact_id, :safety_request_id, :recipient_delivery_id, :recipient_portal_code_id,
				:mail_type, :idempotency_key, :reminder_number, :recipient_email, :subject, :body_text,
				\'queued\', 0, :max_attempts, :available_at, UTC_TIMESTAMP(), UTC_TIMESTAMP()
			)
			ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
		');
		$statement->execute([
			'user_id' => (int)$message['user_id'],
			'check_cycle_id' => $message['check_cycle_id'],
			'monitor_id' => $message['monitor_id'],
			'contact_id' => $message['contact_id'],
			'safety_request_id' => $message['safety_request_id'],
			'recipient_delivery_id' => $message['recipient_delivery_id'],
			'recipient_portal_code_id' => $message['recipient_portal_code_id'] ?? null,
			'mail_type' => (string)$message['mail_type'],
			'idempotency_key' => (string)$message['idempotency_key'],
			'reminder_number' => $message['reminder_number'],
			'recipient_email' => (string)$message['recipient_email'],
			'subject' => (string)$message['subject'],
			'body_text' => (string)$message['body_text'],
			'max_attempts' => $this->_maxAttempts,
			'available_at' => (string)$message['available_at'],
		]);
		$lookup = $connection->prepare('SELECT id FROM mail_queue WHERE idempotency_key = :key LIMIT 1');
		$lookup->execute(['key' => (string)$message['idempotency_key']]);
		$queueId = (int)$lookup->fetchColumn();

		if ($queueId <= 0)
		{
			throw new RuntimeException('Escalation queue job could not be resolved.');
		}

		return $queueId;
	}

	/** @brief Stores the checked address snapshot used by a safety-contact request. */
	private function InsertSafetyRequestEmails(PDO $connection, int $requestId, array $emails): void
	{
		$insert = $connection->prepare('
			INSERT INTO safety_contact_request_emails (safety_request_id, sort_order, email)
			VALUES (:request_id, :sort_order, :email)
		');

		foreach ($emails as $index => $email)
		{
			$insert->execute([
				'request_id' => $requestId,
				'sort_order' => $index + 1,
				'email' => $email,
			]);
		}
	}

	/** @return array<int, string> @brief Returns the snapshotted safety-request addresses. */
	private function FindSafetyRequestEmails(PDO $connection, int $requestId, string $fallback): array
	{
		$statement = $connection->prepare('
			SELECT email FROM safety_contact_request_emails
			WHERE safety_request_id = :request_id
			ORDER BY sort_order ASC
		');
		$statement->execute(['request_id' => $requestId]);
		$emails = $statement->fetchAll(PDO::FETCH_COLUMN);

		return is_array($emails) && $emails !== [] ? array_map('strval', $emails) : [$fallback];
	}

	/** @brief Stores the checked recipient addresses released with one portal delivery. */
	private function InsertRecipientDeliveryEmails(PDO $connection, int $deliveryId, array $emails): void
	{
		$insert = $connection->prepare('
			INSERT INTO recipient_release_delivery_emails (recipient_delivery_id, sort_order, email)
			VALUES (:delivery_id, :sort_order, :email)
		');

		foreach ($emails as $index => $email)
		{
			$insert->execute([
				'delivery_id' => $deliveryId,
				'sort_order' => $index + 1,
				'email' => $email,
			]);
		}
	}

	/** @brief Stores one expiring safety-link token as a hash inside the caller's transaction. */
	private function InsertSafetyToken(PDO $connection, int $requestId, string $rawToken, string $expiresAt): void
	{
		$statement = $connection->prepare('
			INSERT INTO safety_request_tokens (safety_request_id, token_hash, expires_at, created_at)
			VALUES (:safety_request_id, :token_hash, :expires_at, UTC_TIMESTAMP())
		');
		$statement->execute([
			'safety_request_id' => $requestId,
			'token_hash' => hash('sha256', $rawToken),
			'expires_at' => $expiresAt,
		]);
	}

	/** @brief Writes a content-free audit entry. */
	private function WriteAudit(PDO $connection, array $context, string $eventType, array $eventContext = []): void
	{
		$statement = $connection->prepare('
			INSERT INTO audit_log
			(user_id, event_type, entity_type, entity_id, message, context_json, created_at)
			VALUES (:user_id, :event_type, \'monitor\', :entity_id, :message, :context_json, UTC_TIMESTAMP())
		');
		$statement->execute([
			'user_id' => (int)$context['user_id'],
			'event_type' => $eventType,
			'entity_id' => (int)$context['monitor_id'],
			'message' => $eventType,
			'context_json' => $eventContext === [] ? null : json_encode($eventContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
		]);
	}

	/** @brief Calculates the due time of a numbered safety reminder. */
	private function SafetyReminderTime(array $cycle, int $number): DateTimeImmutable
	{
		$start = new DateTimeImmutable((string)$cycle['safety_gate_started_at'], new DateTimeZone('UTC'));
		$days = (int)$cycle['safety_response_window_days']
			+ ((int)$cycle['safety_reminder_interval_days'] * max(0, $number - 1));

		return $start->add(new DateInterval('P' . max(0, $days) . 'D'));
	}

	/** @brief Gives safety links enough lifetime for the configured gate plus operational delay. */
	private function SafetyTokenExpiry(array $cycle): string
	{
		$days = (int)$cycle['safety_response_window_days']
			+ ((int)$cycle['safety_reminder_interval_days'] * (int)$cycle['safety_max_reminders'])
			+ 30;

		return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
			->add(new DateInterval('P' . max(1, $days) . 'D'))
			->format('Y-m-d H:i:s');
	}

	/** @brief Checks the exact representation of generated tokens before hashing. */
	private function IsTokenShapeValid(string $rawToken): bool
	{
		return preg_match('/^[a-f0-9]{64}$/D', $rawToken) === 1;
	}

	/** @brief Returns the current UTC database timestamp. */
	private function Now(): string
	{
		return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
	}
}
