<?php

/**
 * @file EmergencyStopService.php
 * @brief Owner-authorized emergency suspension of all releases and recipient portal access.
 */

declare(strict_types=1);

namespace Pulse\Services;

use PDO;
use Pulse\Core\Database;
use Pulse\Core\Logger;
use Throwable;

/** @brief Transactional emergency stop, bearer-token verification, and explicit reactivation. */
final class EmergencyStopService
{
	private Database $_database;
	private MonitorExecutionService $_execution;
	private Logger $_logger;

	/** @brief Constructs the emergency-stop service. */
	public function __construct(Database $database, MonitorExecutionService $execution, Logger $logger)
	{
		$this->_database = $database;
		$this->_execution = $execution;
		$this->_logger = $logger;
	}

	/**
	 * @brief Resolves a currently valid stop token without consuming it.
	 * @param string $token Raw 256-bit token.
	 * @return array<string, mixed>|null
	 */
	public function FindToken(string $token): ?array
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $token))
		{
			return null;
		}
		$statement = $this->_database->GetConnection()->prepare('
			SELECT rw.id, rw.user_id, rw.monitor_id, m.name AS monitor_name
			FROM release_warnings rw
			INNER JOIN monitors m ON m.id = rw.monitor_id
			INNER JOIN check_cycles cc ON cc.id = rw.check_cycle_id
			INNER JOIN users u ON u.id = rw.user_id
			WHERE rw.token_hash = :hash AND rw.stopped_at IS NULL
				AND rw.expires_at > UTC_TIMESTAMP() AND u.is_active = 1
				AND m.emergency_stopped_at IS NULL
				AND cc.status IN (\'overdue\',\'escalated\')
			LIMIT 1
		');
		$statement->execute(['hash' => hash('sha256', $token)]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	/**
	 * @brief Suspends every monitor owned by an authenticated user.
	 * @return int Number of previously non-stopped monitors.
	 */
	public function StopForUser(int $userId): int
	{
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();
		try
		{
			$count = $this->StopLocked($connection, $userId, 'dashboard');
			$connection->commit();
			return $count;
		}
		catch (Throwable $error)
		{
			$connection->rollBack();
			throw $error;
		}
	}

	/**
	 * @brief Consumes an expiring emergency link and stops only its target monitor.
	 * @return bool Whether the token authorized a stop.
	 */
	public function StopByToken(string $token): bool
	{
		if (!preg_match('/^[a-f0-9]{64}$/D', $token))
		{
			return false;
		}
		$connection = $this->_database->GetConnection();
		$connection->beginTransaction();
		try
		{
			// Resolve the target first, then serialize with dashboard stops by locking its monitor.
			// The token is checked again after the monitor lock, so it is strictly one-use.
			$statement = $connection->prepare('
				SELECT rw.user_id, rw.monitor_id
				FROM release_warnings rw
				WHERE rw.token_hash = :hash
				LIMIT 1
			');
			$statement->execute(['hash' => hash('sha256', $token)]);
			$target = $statement->fetch(PDO::FETCH_ASSOC);
			if (!is_array($target))
			{
				$connection->commit();
				return false;
			}
			$monitorId = (int)$target['monitor_id'];
			$userId = (int)$target['user_id'];
			$monitor = $connection->prepare('SELECT id FROM monitors WHERE id = :monitor_id AND user_id = :user_id AND emergency_stopped_at IS NULL FOR UPDATE');
			$monitor->execute(['monitor_id' => $monitorId, 'user_id' => $userId]);
			if (!$monitor->fetchColumn())
			{
				$connection->commit();
				return false;
			}
			$verify = $connection->prepare('
				SELECT rw.id
				FROM release_warnings rw
				INNER JOIN check_cycles cc ON cc.id = rw.check_cycle_id
				INNER JOIN users u ON u.id = rw.user_id
				WHERE rw.token_hash = :hash AND rw.user_id = :user_id
					AND rw.monitor_id = :monitor_id AND rw.stopped_at IS NULL
					AND rw.expires_at > UTC_TIMESTAMP() AND u.is_active = 1
					AND cc.status IN (\'overdue\',\'escalated\')
				FOR UPDATE
			');
			$verify->execute(['hash' => hash('sha256', $token), 'user_id' => $userId, 'monitor_id' => $monitorId]);
			if (!$verify->fetchColumn())
			{
				$connection->commit();
				return false;
			}
			$this->StopLocked($connection, $userId, 'email_link', $monitorId);
			$connection->commit();
			return true;
		}
		catch (Throwable $error)
		{
			$connection->rollBack();
			throw $error;
		}
	}

	/**
	 * @brief Explicitly restarts monitors previously stopped by the owner.
	 * @details Monitors that were already paused before the emergency stay paused.
	 * @return int Count of monitors returned to normal operation.
	 */
	public function ReactivateForUser(int $userId): int
	{
		$connection = $this->_database->GetConnection();
		$statement = $connection->prepare('
			SELECT id, emergency_was_paused
			FROM monitors WHERE user_id = :user_id AND emergency_stopped_at IS NOT NULL
		');
		$statement->execute(['user_id' => $userId]);
		$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		$restarted = 0;
		foreach (is_array($rows) ? $rows : [] as $row)
		{
			$monitorId = (int)$row['id'];
			$update = $connection->prepare('
				UPDATE monitors
				SET emergency_stopped_at = NULL, emergency_was_paused = 0, updated_at = UTC_TIMESTAMP()
				WHERE id = :id AND user_id = :user_id AND emergency_stopped_at IS NOT NULL
			');
			$update->execute(['id' => $monitorId, 'user_id' => $userId]);
			if ((int)$row['emergency_was_paused'] === 0)
			{
				try
				{
					if ($this->_execution->ResumeMonitorForUser($monitorId, $userId))
					{
						$restarted++;
					}
					else
					{
						$this->RestoreEmergencyFlag($connection, $monitorId, $userId);
					}
				}
				catch (Throwable $error)
				{
					$this->RestoreEmergencyFlag($connection, $monitorId, $userId);
					throw $error;
				}
			}
		}
		$this->_logger->Info('Emergency suspension reactivated by authenticated owner', ['user_id' => $userId, 'restarted' => $restarted]);
		return $restarted;
	}

	/**
	 * @brief Keeps a failed reactivation safely suspended so that it can be retried.
	 * @param PDO $connection Active database connection.
	 * @param int $monitorId Monitor to protect.
	 * @param int $userId Owner of that monitor.
	 */
	private function RestoreEmergencyFlag(PDO $connection, int $monitorId, int $userId): void
	{
		$statement = $connection->prepare('UPDATE monitors SET emergency_stopped_at = UTC_TIMESTAMP(), emergency_was_paused = 0, is_paused = 1, updated_at = UTC_TIMESTAMP() WHERE id = :id AND user_id = :user_id AND is_paused = 1');
		$statement->execute(['id' => $monitorId, 'user_id' => $userId]);
	}

	/**
	 * @brief Stops the selected monitor or all owner monitors, associated mail, and released portals in one transaction.
	 * @param PDO $connection Transactional PDO connection.
	 * @param int $userId Target account.
	 * @param string $source Authorized entry point.
	 */
	private function StopLocked(PDO $connection, int $userId, string $source, ?int $monitorId = null): int
	{
		$single = $monitorId !== null;
		$whereMonitor = $single ? ' AND id = :monitor_id' : '';
		$params = ['user_id' => $userId];
		if ($single)
		{
			$params['monitor_id'] = $monitorId;
		}
		$monitors = $connection->prepare('SELECT id FROM monitors WHERE user_id = :user_id' . $whereMonitor . ' FOR UPDATE');
		$monitors->execute($params);
		$ids = array_map('intval', $monitors->fetchAll(PDO::FETCH_COLUMN));
		if ($ids === [])
		{
			return 0;
		}
		$now = gmdate('Y-m-d H:i:s');
		$update = $connection->prepare('
			UPDATE monitors SET
				emergency_was_paused = CASE WHEN emergency_stopped_at IS NULL THEN is_paused ELSE emergency_was_paused END,
				emergency_stopped_at = COALESCE(emergency_stopped_at, :stopped_at),
				is_paused = 1, paused_at = COALESCE(paused_at, :paused_at),
				next_check_due_at = NULL, updated_at = :updated_at
			WHERE user_id = :user_id' . $whereMonitor);
		$update->execute(array_merge($params, ['stopped_at' => $now, 'paused_at' => $now, 'updated_at' => $now]));
		$cycle = $connection->prepare('
			UPDATE check_cycles cc INNER JOIN monitors m ON m.id = cc.monitor_id
			SET cc.status = \'cancelled\', cc.cancelled_at = :now, cc.updated_at = :updated_at
			WHERE m.user_id = :user_id' . ($single ? ' AND m.id = :monitor_id' : '') . '
				AND cc.status IN (\'scheduled\',\'awaiting\',\'safety_pending\',\'overdue\',\'escalated\')');
		$cycle->execute(array_merge($params, ['now' => $now, 'updated_at' => $now]));
		// Only monitor-related messages are cancelled. Other account mail (e.g. login)
		// must not be inadvertently suppressed by an emergency stop.
		$cancelMail = $connection->prepare('
			UPDATE mail_queue SET status = \'cancelled\', cancelled_at = :cancelled_at,
				body_text = \'[Cancelled by emergency stop]\',
				locked_by = NULL, locked_at = NULL, locked_until = NULL, lease_token = NULL,
				updated_at = :updated_at
			WHERE user_id = :user_id AND monitor_id IS NOT NULL' . ($single ? ' AND monitor_id = :monitor_id' : '') . '
				AND status IN (\'queued\',\'retrying\',\'processing\')');
		$cancelMail->execute(array_merge($params, ['cancelled_at' => $now, 'updated_at' => $now]));
		$revoke = $connection->prepare('
			UPDATE recipient_release_deliveries rrd INNER JOIN monitors m ON m.id = rrd.monitor_id
			SET rrd.portal_revoked_at = COALESCE(rrd.portal_revoked_at, :revoked_at),
				rrd.cancelled_at = CASE WHEN rrd.status = \'queued\' THEN :cancelled_at ELSE rrd.cancelled_at END,
				rrd.status = CASE WHEN rrd.status = \'queued\' THEN \'cancelled\' ELSE rrd.status END,
				rrd.updated_at = :updated_at
			WHERE m.user_id = :user_id' . ($single ? ' AND m.id = :monitor_id' : ''));
		$revoke->execute(array_merge($params, ['revoked_at' => $now, 'cancelled_at' => $now, 'updated_at' => $now]));
		$codes = $connection->prepare('
			UPDATE recipient_portal_codes rpc
			INNER JOIN recipient_release_deliveries rrd ON rrd.id = rpc.recipient_delivery_id
			INNER JOIN monitors m ON m.id = rrd.monitor_id
			SET rpc.invalidated_at = COALESCE(rpc.invalidated_at, :now), rpc.updated_at = :updated_at
			WHERE m.user_id = :user_id' . ($single ? ' AND m.id = :monitor_id' : '') . '
				AND rpc.used_at IS NULL');
		$codes->execute(array_merge($params, ['now' => $now, 'updated_at' => $now]));
		$release = $connection->prepare('
			UPDATE recipient_releases SET status = \'cancelled\', cancelled_at = :cancelled_at,
				updated_at = :updated_at WHERE user_id = :user_id' . ($single ? ' AND monitor_id = :monitor_id' : '') . '
				AND status <> \'cancelled\'');
		$release->execute(array_merge($params, ['cancelled_at' => $now, 'updated_at' => $now]));
		$warnings = $connection->prepare('
			UPDATE release_warnings SET stopped_at = COALESCE(stopped_at, :now), updated_at = :updated_at
			WHERE user_id = :user_id' . ($single ? ' AND monitor_id = :monitor_id' : ''));
		$warnings->execute(array_merge($params, ['now' => $now, 'updated_at' => $now]));
		$audit = $connection->prepare('
			INSERT INTO audit_log (user_id, event_type, entity_type, entity_id, message, context_json, created_at)
			VALUES (:user_id, \'monitor.emergency_stopped\', \'monitor\', :monitor_id, :message, :context, :created_at)
		');
		foreach ($ids as $id)
		{
			$audit->execute([
				'user_id' => $userId,
				'monitor_id' => $id,
				'message' => 'Emergency stop activated',
				'context' => json_encode(['source' => $source, 'monitor_count' => count($ids)], JSON_THROW_ON_ERROR),
				'created_at' => $now,
			]);
		}
		$this->_logger->Warning('Owner emergency stop activated', ['user_id' => $userId, 'monitor_id' => $monitorId, 'source' => $source]);
		return count($ids);
	}
}
