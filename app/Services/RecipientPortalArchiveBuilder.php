<?php

/**
 * @file RecipientPortalArchiveBuilder.php
 * @brief Streams portable store-only ZIP/ZIP64 archives without requiring the PHP zip extension.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Services;

use RuntimeException;

/**
 * @brief Streams one recipient delivery's immutable document snapshot as a ZIP archive.
 */
final class RecipientPortalArchiveBuilder
{
	private const UINT16_MAX = 0xffff;
	private const UINT32_MAX = 0xffffffff;
	private const COPY_CHUNK_BYTES = 1048576;

	private DocumentService $_documentService;
	private int $_bytesWritten = 0;

	/**
	 * @brief Constructs the archive builder.
	 * @param DocumentService $documentService Private document storage resolver.
	 */
	public function __construct(DocumentService $documentService)
	{
		$this->_documentService = $documentService;
	}

	/**
	 * @brief Streams an archive directly to a writable output stream.
	 * @param array<int, array<string, mixed>> $documents Recipient delivery snapshots.
	 * @param resource $output Writable destination such as php://output.
	 * @return int Number of documents written to the archive.
	 */
	public function Stream(array $documents, $output): int
	{
		if (!is_resource($output))
		{
			throw new RuntimeException('Recipient portal ZIP output is not writable.');
		}

		$entries = $this->PrepareEntries($documents);

		if ($entries === [])
		{
			throw new RuntimeException('No recipient portal documents are available for the archive.');
		}

		$this->_bytesWritten = 0;
		$centralEntries = [];
		[$dosTime, $dosDate] = $this->DosDateTime();

		foreach ($entries as $entry)
		{
			$centralEntries[] = $this->WriteEntry($output, $entry, $dosTime, $dosDate);
		}

		$this->WriteCentralDirectory($output, $centralEntries);
		return count($centralEntries);
	}

	/**
	 * @brief Resolves document payloads and safe unique archive names before any response body is streamed.
	 * @param array<int, array<string, mixed>> $documents Recipient delivery snapshots.
	 * @return array<int, array<string, mixed>> Prepared archive entries.
	 */
	private function PrepareEntries(array $documents): array
	{
		$entries = [];
		$usedNames = [];

		foreach ($documents as $document)
		{
			$name = $this->UniqueFilename($this->DocumentFilename($document), $usedNames);

			if ((string)($document['storage_type'] ?? '') === 'text')
			{
				$data = (string)($document['text_content'] ?? '');
				$entries[] = [
					'name' => $name,
					'kind' => 'text',
					'data' => $data,
					'size' => strlen($data),
				];
				continue;
			}

			$filePath = $this->_documentService->ResolvePortalSnapshotFile($document);

			if ($filePath === null || !is_readable($filePath))
			{
				continue;
			}

			$fileSize = filesize($filePath);

			if (!is_int($fileSize) || $fileSize < 0)
			{
				continue;
			}

			$entries[] = [
				'name' => $name,
				'kind' => 'file',
				'path' => $filePath,
				'size' => $fileSize,
			];
		}

		return $entries;
	}

	/**
	 * @brief Writes one local entry and returns metadata needed by the central directory.
	 * @param resource $output Writable archive stream.
	 * @param array<string, mixed> $entry Prepared entry.
	 * @return array<string, mixed> Central-directory metadata.
	 */
	private function WriteEntry($output, array $entry, int $dosTime, int $dosDate): array
	{
		$name = (string)$entry['name'];
		$size = (int)$entry['size'];
		$offset = $this->_bytesWritten;
		$zip64Size = $size >= self::UINT32_MAX;
		$versionNeeded = $zip64Size ? 45 : 20;
		$flags = 0x0808; // UTF-8 + data descriptor.
		$localExtra = '';

		if ($zip64Size)
		{
			$zip64Payload = $this->PackUInt64($size) . $this->PackUInt64($size);
			$localExtra = pack('vv', 0x0001, strlen($zip64Payload)) . $zip64Payload;
		}

		$sizeField = $zip64Size ? self::UINT32_MAX : 0;
		$localHeader = pack(
			'VvvvvvVVVvv',
			0x04034b50,
			$versionNeeded,
			$flags,
			0,
			$dosTime,
			$dosDate,
			0,
			$sizeField,
			$sizeField,
			strlen($name),
			strlen($localExtra)
		);
		$this->WriteAll($output, $localHeader . $name . $localExtra);

		$hash = hash_init('crc32b');
		$actualSize = 0;

		if ((string)$entry['kind'] === 'text')
		{
			$data = (string)$entry['data'];
			hash_update($hash, $data);
			$this->WriteAll($output, $data);
			$actualSize = strlen($data);
		}
		else
		{
			$source = fopen((string)$entry['path'], 'rb');

			if ($source === false)
			{
				throw new RuntimeException('Unable to open a recipient portal document for ZIP streaming.');
			}

			try
			{
				while (!feof($source))
				{
					$chunk = fread($source, self::COPY_CHUNK_BYTES);

					if ($chunk === false)
					{
						throw new RuntimeException('Unable to read a recipient portal document while streaming ZIP data.');
					}

					if ($chunk === '')
					{
						continue;
					}

					hash_update($hash, $chunk);
					$this->WriteAll($output, $chunk);
					$actualSize += strlen($chunk);
				}
			}
			finally
			{
				fclose($source);
			}
		}

		if ($actualSize !== $size)
		{
			throw new RuntimeException('A recipient portal document changed while its ZIP archive was being streamed.');
		}

		$crcHex = hash_final($hash);
		$crc = (int)hexdec($crcHex);
		$descriptor = pack('VV', 0x08074b50, $crc);

		if ($zip64Size)
		{
			$descriptor .= $this->PackUInt64($size) . $this->PackUInt64($size);
		}
		else
		{
			$descriptor .= pack('VV', $size, $size);
		}

		$this->WriteAll($output, $descriptor);

		return [
			'name' => $name,
			'crc' => $crc,
			'size' => $size,
			'offset' => $offset,
			'time' => $dosTime,
			'date' => $dosDate,
		];
	}

	/**
	 * @brief Writes the central directory and ZIP64 end structures when required.
	 * @param resource $output Writable archive stream.
	 * @param array<int, array<string, mixed>> $entries Written entry metadata.
	 */
	private function WriteCentralDirectory($output, array $entries): void
	{
		$centralOffset = $this->_bytesWritten;

		foreach ($entries as $entry)
		{
			$name = (string)$entry['name'];
			$size = (int)$entry['size'];
			$offset = (int)$entry['offset'];
			$zip64Size = $size >= self::UINT32_MAX;
			$zip64Offset = $offset >= self::UINT32_MAX;
			$zip64 = $zip64Size || $zip64Offset;
			$extraPayload = '';

			if ($zip64Size)
			{
				$extraPayload .= $this->PackUInt64($size) . $this->PackUInt64($size);
			}

			if ($zip64Offset)
			{
				$extraPayload .= $this->PackUInt64($offset);
			}

			$extra = $extraPayload !== '' ? pack('vv', 0x0001, strlen($extraPayload)) . $extraPayload : '';
			$version = $zip64 ? 45 : 20;
			$header = pack(
				'VvvvvvvVVVvvvvvVV',
				0x02014b50,
				$version,
				$version,
				0x0808,
				0,
				(int)$entry['time'],
				(int)$entry['date'],
				(int)$entry['crc'],
				$zip64Size ? self::UINT32_MAX : $size,
				$zip64Size ? self::UINT32_MAX : $size,
				strlen($name),
				strlen($extra),
				0,
				0,
				0,
				0,
				$zip64Offset ? self::UINT32_MAX : $offset
			);
			$this->WriteAll($output, $header . $name . $extra);
		}

		$centralSize = $this->_bytesWritten - $centralOffset;
		$count = count($entries);
		$needsZip64 = $count >= self::UINT16_MAX
			|| $centralSize >= self::UINT32_MAX
			|| $centralOffset >= self::UINT32_MAX;

		if ($needsZip64)
		{
			$zip64EndOffset = $this->_bytesWritten;
			$zip64End = pack('V', 0x06064b50)
				. $this->PackUInt64(44)
				. pack('vvVV', 45, 45, 0, 0)
				. $this->PackUInt64($count)
				. $this->PackUInt64($count)
				. $this->PackUInt64($centralSize)
				. $this->PackUInt64($centralOffset);
			$this->WriteAll($output, $zip64End);
			$this->WriteAll(
				$output,
				pack('VV', 0x07064b50, 0) . $this->PackUInt64($zip64EndOffset) . pack('V', 1)
			);
		}

		$this->WriteAll(
			$output,
			pack(
				'VvvvvVVv',
				0x06054b50,
				0,
				0,
				$count >= self::UINT16_MAX ? self::UINT16_MAX : $count,
				$count >= self::UINT16_MAX ? self::UINT16_MAX : $count,
				$centralSize >= self::UINT32_MAX ? self::UINT32_MAX : $centralSize,
				$centralOffset >= self::UINT32_MAX ? self::UINT32_MAX : $centralOffset,
				0
			)
		);
	}

	/** @brief Writes a complete binary string and tracks the streamed archive offset. @param resource $output Writable stream. */
	private function WriteAll($output, string $data): void
	{
		$length = strlen($data);
		$written = 0;

		while ($written < $length)
		{
			$count = fwrite($output, substr($data, $written));

			if (!is_int($count) || $count <= 0)
			{
				throw new RuntimeException('Unable to stream recipient portal ZIP data.');
			}

			$written += $count;
			$this->_bytesWritten += $count;
		}
	}

	/** @brief Packs one non-negative integer as an unsigned little-endian 64-bit value. */
	private function PackUInt64(int $value): string
	{
		if ($value < 0)
		{
			throw new RuntimeException('ZIP64 values cannot be negative.');
		}

		$low = $value % 0x100000000;
		$high = intdiv($value, 0x100000000);
		return pack('VV', $low, $high);
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
			return str_ends_with(strtolower($title), '.md')
				? $title
				: (str_ends_with(strtolower($title), '.txt') ? substr($title, 0, -4) . '.md' : $title . '.md');
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
}
