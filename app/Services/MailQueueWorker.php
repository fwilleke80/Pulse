<?php

/**
 * @file MailQueueWorker.php
 * @brief Leased mail-queue worker with bounded exponential retries.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use Pulse\Core\Logger;
use Pulse\Mail\MailTransportException;
use Pulse\Mail\MailTransportInterface;
use Pulse\Repositories\MailQueueRepository;
use Throwable;

/**
 * @brief Claims durable jobs, sends their snapshots, and commits attempt outcomes.
 */
final class MailQueueWorker
{
	private MailQueueRepository $_queue;
	private MailTransportInterface $_transport;
	private Logger $_logger;
	private int $_leaseSeconds;

	/** @var array<int> */
	private array $_retryDelays;

	/** @brief Constructs the worker. */
	public function __construct(
		MailQueueRepository $queue,
		MailTransportInterface $transport,
		Logger $logger,
		int $leaseSeconds,
		array $retryDelays
	)
	{
		$this->_queue = $queue;
		$this->_transport = $transport;
		$this->_logger = $logger;
		$this->_leaseSeconds = $leaseSeconds;
		$this->_retryDelays = array_map('intval', $retryDelays);
	}

	/**
	 * @brief Processes a concurrent-safe batch.
	 * @return array{recovered: int, claimed: int, sent: int, retrying: int, failed: int, cancelled: int}
	 */
	public function Process(int $limit): array
	{
		$workerId = $this->WorkerId();
		$recovered = $this->_queue->RecoverExpiredLeases();
		$jobs = $this->_queue->ClaimBatch($workerId, $limit, $this->_leaseSeconds);
		$result = ['recovered' => $recovered, 'claimed' => count($jobs), 'sent' => 0, 'retrying' => 0, 'failed' => 0, 'cancelled' => 0];

		foreach ($jobs as $job)
		{
			$status = $this->Deliver($job, $workerId);
			$result[$status]++;
		}

		return $result;
	}

	/**
	 * @brief Processes one selected test job immediately through the normal queue path.
	 * @return string sent, retrying, failed, cancelled, or queued.
	 */
	public function ProcessById(int $jobId): string
	{
		$workerId = $this->WorkerId();
		$this->_queue->RecoverExpiredLeases();
		$job = $this->_queue->ClaimById($jobId, $workerId, $this->_leaseSeconds);

		if (!is_array($job))
		{
			$current = $this->_queue->FindById($jobId);
			return is_array($current) ? (string)$current['status'] : 'queued';
		}

		return $this->Deliver($job, $workerId);
	}


	/**
	 * @brief Bypasses retry backoff for one explicit debug delivery action.
	 * @param int $jobId Queue job ID.
	 * @return bool True when the job can be retried immediately.
	 */
	public function PrepareImmediateDebugRetry(int $jobId): bool
	{
		return $this->_queue->PrepareImmediateDebugRetry($jobId);
	}

	/** @brief Performs one transport attempt and persists the result. */
	private function Deliver(array $job, string $workerId): string
	{
		if (!$this->_queue->IsStillDeliverable($job))
		{
			$this->_queue->CancelClaim((int)$job['id'], $workerId);
			return 'cancelled';
		}

		try
		{
			$this->_transport->Send(
				(string)$job['recipient_email'],
				(string)$job['subject'],
				(string)$job['body_text']
			);
			$this->_queue->MarkSent((int)$job['id'], $workerId);
			$this->_logger->Info('Queued mail sent', [
				'queue_id' => (int)$job['id'],
				'mail_type' => (string)$job['mail_type'],
				'attempt' => (int)$job['attempt_count'],
			]);
			return 'sent';
		}
		catch (Throwable $throwable)
		{
			$delay = $this->RetryDelay((int)$job['attempt_count']);
			$error = $throwable instanceof MailTransportException
				? $throwable->getMessage()
				: 'Unexpected mail worker failure.';
			$status = $this->_queue->MarkFailedAttempt(
				(int)$job['id'],
				$workerId,
				$error,
				$delay
			);
			$this->_logger->Warning('Queued mail attempt failed', [
				'queue_id' => (int)$job['id'],
				'mail_type' => (string)$job['mail_type'],
				'attempt' => (int)$job['attempt_count'],
				'queue_status' => $status,
				'error' => $error,
			]);
			return in_array($status, ['retrying', 'failed'], true) ? $status : 'failed';
		}
	}

	/** @brief Selects the configured delay for the completed attempt number. */
	private function RetryDelay(int $attempt): int
	{
		if ($this->_retryDelays === [])
		{
			return 60;
		}

		$index = min(count($this->_retryDelays) - 1, max(0, $attempt - 1));
		return max(0, $this->_retryDelays[$index]);
	}

	/** @brief Returns a unique, log-safe worker run identifier. */
	private function WorkerId(): string
	{
		$host = gethostname();
		$host = is_string($host) ? preg_replace('/[^a-zA-Z0-9_.-]/', '-', $host) : 'host';
		return substr((string)$host . ':' . getmypid() . ':' . bin2hex(random_bytes(8)), 0, 64);
	}
}
