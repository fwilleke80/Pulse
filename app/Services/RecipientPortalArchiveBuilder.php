<?php

/**
 * @file RecipientPortalArchiveBuilder.php
 * @brief Builds portable store-only ZIP archives without requiring the PHP zip extension.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use RuntimeException;

/**
 * @brief Creates a temporary ZIP containing one recipient delivery's immutable document snapshot.
 */
final class RecipientPortalArchiveBuilder
{
	private DocumentService $_documentService;
	private string $_temporaryDirectory;

	/**
	 * @brief Constructs the archive builder.
	 * @param DocumentService $documentService Private document storage resolver.
	 * @param string $temporaryDirectory Private temporary directory.
	 */
	public function __construct(DocumentService $documentService, string $temporaryDirectory)
	{
		$this->_documentService = $documentService;
		$this->_temporaryDirectory = rtrim($temporaryDirectory, '/\\');
	}

	/**
	 * @brief Builds a temporary archive and returns its path.
	 * @param array<int, array<string, mixed>> $documents Recipient delivery snapshots.
	 * @return string Absolute temporary ZIP path.
	 */
	public function Build(array $documents): string
	{
		$this->EnsureTemporaryDirectory();
		$path = tempnam($this->_temporaryDirectory, 'pulse-portal-');

		if (!is_string($path) || $path === '')
		{
			throw new RuntimeException('Unable to allocate recipient portal archive.');
		}

		$handle = fopen($path, 'w+b');

		if ($handle === false)
		{
			@unlink($path);
			throw new RuntimeException('Unable to open recipient portal archive.');
		}

		try
		{
			$this->WriteArchive($handle, $documents);
			fflush($handle);
		}
		catch (\Throwable $throwable)
		{
			fclose($handle);
			@unlink($path);
			throw $throwable;
		}

		fclose($handle);
		return $path;
	}

	/**
	 * @brief Writes the ZIP structures and payloads.
	 * @param resource $handle Writable archive stream.
	 * @param array<int, array<string, mixed>> $documents Recipient snapshot documents.
	 */
	private function WriteArchive($handle, array $documents): void
	{
		$entries = [];
		$usedNames = [];
		[$dosTime, $dosDate] = $this->DosDateTime();

		foreach ($documents as $document)
		{
			$name = $this->UniqueFilename($this->DocumentFilename($document), $usedNames);
			$offset = ftell($handle);

			if (!is_int($offset))
			{
				throw new RuntimeException('Unable to determine ZIP archive offset.');
			}

			if ((string)($document['storage_type'] ?? '') === 'text')
			{
				$data = (string)($document['text_content'] ?? '');
				$size = strlen($data);
				$crc = (int)hexdec(hash('crc32b', $data));
				$this->WriteLocalHeader($handle, $name, $crc, $size, $dosTime, $dosDate);
				$this->WriteAll($handle, $data);
			}
			else
			{
				$filePath = $this->_documentService->ResolvePortalSnapshotFile($document);

				if ($filePath === null)
				{
					continue;
				}

				$fileSize = filesize($filePath);

				if (!is_int($fileSize) || $fileSize < 0 || $fileSize > 0xffffffff)
				{
					continue;
				}

				$crcHex = hash_file('crc32b', $filePath);

				if (!is_string($crcHex))
				{
					continue;
				}

				$source = fopen($filePath, 'rb');

				if ($source === false)
				{
					continue;
				}

				$size = $fileSize;
				$crc = (int)hexdec($crcHex);
				$this->WriteLocalHeader($handle, $name, $crc, $size, $dosTime, $dosDate);
				$copied = stream_copy_to_stream($source, $handle);
				fclose($source);

				if (!is_int($copied) || $copied !== $size)
				{
					throw new RuntimeException('Unable to read a complete recipient portal document.');
				}
			}

			$entries[] = [
				'name' => $name,
				'crc' => $crc,
				'size' => $size,
				'offset' => $offset,
				'time' => $dosTime,
				'date' => $dosDate,
			];
		}

		$centralOffset = ftell($handle);

		if (!is_int($centralOffset))
		{
			throw new RuntimeException('Unable to determine ZIP central-directory offset.');
		}

		foreach ($entries as $entry)
		{
			$name = (string)$entry['name'];
			$header = pack(
				'VvvvvvvVVVvvvvvVV',
				0x02014b50,
				20,
				20,
				0x0800,
				0,
				(int)$entry['time'],
				(int)$entry['date'],
				(int)$entry['crc'],
				(int)$entry['size'],
				(int)$entry['size'],
				strlen($name),
				0,
				0,
				0,
				0,
				0,
				(int)$entry['offset']
			);
			$this->WriteAll($handle, $header . $name);
		}

		$endOffset = ftell($handle);

		if (!is_int($endOffset))
		{
			throw new RuntimeException('Unable to determine ZIP end offset.');
		}

		$centralSize = $endOffset - $centralOffset;
		$count = count($entries);
		$this->WriteAll($handle, pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $centralOffset, 0));
	}

	/** @brief Writes one local ZIP file header. @param resource $handle Archive stream. */
	private function WriteLocalHeader($handle, string $name, int $crc, int $size, int $dosTime, int $dosDate): void
	{
		$header = pack(
			'VvvvvvVVVvv',
			0x04034b50,
			20,
			0x0800,
			0,
			$dosTime,
			$dosDate,
			$crc,
			$size,
			$size,
			strlen($name),
			0
		);
		$this->WriteAll($handle, $header . $name);
	}

	/** @brief Writes a complete binary string or fails. @param resource $handle Output stream. */
	private function WriteAll($handle, string $data): void
	{
		$length = strlen($data);
		$written = 0;

		while ($written < $length)
		{
			$count = fwrite($handle, substr($data, $written));

			if (!is_int($count) || $count <= 0)
			{
				throw new RuntimeException('Unable to write recipient portal archive.');
			}

			$written += $count;
		}
	}

	/** @brief Returns an archive filename based on the recipient-facing title. */
	private function DocumentFilename(array $document): string
	{
		$title = trim((string)($document['title'] ?? ''));
		$title = preg_replace('~[\\/\x00-\x1F\x7F]+~u', '-', $title) ?? '';
		$title = trim($title, " .\t\n\r\0\x0B-");

		if ($title === '')
		{
			$title = 'document';
		}

		if ((string)($document['storage_type'] ?? '') === 'text')
		{
			return str_ends_with(strtolower($title), '.txt') ? $title : $title . '.txt';
		}

		$original = basename((string)($document['original_filename'] ?? ''));
		$extension = pathinfo($original, PATHINFO_EXTENSION);

		if ($extension !== '' && pathinfo($title, PATHINFO_EXTENSION) === '')
		{
			$title .= '.' . $extension;
		}

		return $title;
	}

	/** @brief Makes duplicate document names unique inside one archive. @param array<string, bool> $usedNames Used lowercase names. */
	private function UniqueFilename(string $filename, array &$usedNames): string
	{
		$candidate = $filename;
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		$base = $extension === '' ? $filename : substr($filename, 0, -(strlen($extension) + 1));
		$index = 2;

		while (isset($usedNames[strtolower($candidate)]))
		{
			$candidate = $base . ' (' . $index . ')' . ($extension !== '' ? '.' . $extension : '');
			$index++;
		}

		$usedNames[strtolower($candidate)] = true;
		return $candidate;
	}

	/** @return array{0: int, 1: int} @brief Returns the current UTC time in DOS ZIP fields. */
	private function DosDateTime(): array
	{
		$parts = getdate();
		$year = max(1980, min(2107, (int)$parts['year']));
		$dosTime = ((int)$parts['hours'] << 11) | ((int)$parts['minutes'] << 5) | ((int)$parts['seconds'] >> 1);
		$dosDate = (($year - 1980) << 9) | ((int)$parts['mon'] << 5) | (int)$parts['mday'];
		return [$dosTime, $dosDate];
	}

	/** @brief Creates the private temporary directory when necessary. */
	private function EnsureTemporaryDirectory(): void
	{
		if (!is_dir($this->_temporaryDirectory) && !@mkdir($this->_temporaryDirectory, 0700, true) && !is_dir($this->_temporaryDirectory))
		{
			throw new RuntimeException('Unable to create recipient portal temporary directory.');
		}
	}
}
