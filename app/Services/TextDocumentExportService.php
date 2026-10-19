<?php

/**
 * @file TextDocumentExportService.php
 * @brief Ownership-scoped export of current editable Markdown documents.
 */

declare(strict_types=1);

namespace Pulse\Services;

use PDO;
use Phar;
use PharData;
use RuntimeException;

/**
 * @brief Builds a portable ZIP of current Markdown source and metadata.
 */
final class TextDocumentExportService
{
	private PDO $_connection;

	/** @brief Constructs the exporter. @param PDO $connection Active database connection. */
	public function __construct(PDO $connection)
	{
		$this->_connection = $connection;
	}

	/**
	 * @brief Exports one account's current text documents; never exports other users' data.
	 * @param int $userId Authenticated account ID.
	 * @param string $destination Absolute path to a new temporary ZIP file.
	 * @return int Count of documents written.
	 */
	public function ExportForUser(int $userId, string $destination): int
	{
		if ($userId <= 0 || file_exists($destination))
		{
			throw new RuntimeException('Invalid document export destination or user.');
		}

		$query = $this->_connection->prepare('
			SELECT d.id, d.monitor_id, d.title, d.description, d.text_content,
			       d.created_at, d.updated_at, m.name AS monitor_name
			FROM documents d
			INNER JOIN monitors m ON m.id = d.monitor_id
			WHERE m.user_id = :user_id AND d.storage_type = :storage_type
			ORDER BY m.id, d.id
		');
		$query->execute(['user_id' => $userId, 'storage_type' => 'text']);
		$archive = new PharData($destination, 0, null, Phar::ZIP);
		$manifest = [
			'format' => 'pulse-text-documents-v1',
			'created_at' => gmdate('c'),
			'owner_id' => $userId,
			'documents' => [],
		];

		while (($document = $query->fetch(PDO::FETCH_ASSOC)) !== false)
		{
			$monitorId = (int)$document['monitor_id'];
			$documentId = (int)$document['id'];
			$directory = sprintf('monitors/%06d_%s', $monitorId, $this->SafeName((string)$document['monitor_name']));
			$filename = sprintf('%06d_%s.md', $documentId, $this->SafeName((string)$document['title']));
			$path = $directory . '/' . $filename;
			$source = (string)($document['text_content'] ?? '');
			$archive->addFromString($path, $source);
			$manifest['documents'][] = [
				'id' => $documentId,
				'monitor_id' => $monitorId,
				'monitor_name' => (string)$document['monitor_name'],
				'title' => (string)$document['title'],
				'description' => (string)($document['description'] ?? ''),
				'created_at' => $document['created_at'],
				'updated_at' => $document['updated_at'],
				'file' => $path,
				'sha256' => hash('sha256', $source),
			];
		}

		$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		$archive->addFromString('manifest.json', $json . "\n");
		return count($manifest['documents']);
	}

	/** @brief Converts titles into short, traversal-safe filename components. */
	private function SafeName(string $name): string
	{
		$name = preg_replace('/[^\p{L}\p{N} ._-]+/u', '-', $name) ?? '';
		$name = trim(preg_replace('/\s+/u', ' ', $name) ?? '', '. -_');
		$characters = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
		$name = is_array($characters) ? implode('', array_slice($characters, 0, 70)) : '';
		return $name !== '' && $name !== '.' && $name !== '..' ? $name : 'untitled';
	}
}
