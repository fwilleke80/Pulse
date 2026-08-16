<?php

/**
 * @file PrivateFileStreamer.php
 * @brief Non-cacheable authenticated file streaming with single-range support.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Core;

use RuntimeException;

/**
 * @brief Streams authorized private files without exposing their storage paths.
 */
final class PrivateFileStreamer
{
	private const CHUNK_BYTES = 1048576;

	/**
	 * @brief Streams one verified file and terminates the request.
	 * @param string $path Verified private file path.
	 * @param string $filename Recipient-facing filename.
	 * @param string $contentType Verified content type.
	 * @param string $disposition inline or attachment.
	 * @param bool $allowRanges Whether one HTTP byte range may be served.
	 * @param bool $allowSameOriginFrame Whether this response may be embedded by Pulse itself.
	 */
	public function Stream(
		string $path,
		string $filename,
		string $contentType,
		string $disposition = 'inline',
		bool $allowRanges = true,
		bool $allowSameOriginFrame = false
	): never
	{
		if (!is_file($path) || !is_readable($path))
		{
			throw new RuntimeException('Private file is unavailable.');
		}

		$fileSize = filesize($path);

		if (!is_int($fileSize) || $fileSize < 0)
		{
			throw new RuntimeException('Private file size is unavailable.');
		}

		$this->PrepareResponse();
		$this->SendHeaders($filename, $contentType, $disposition, $allowRanges, $allowSameOriginFrame);
		$start = 0;
		$end = max(0, $fileSize - 1);
		$rangeHeader = $allowRanges ? trim((string)($_SERVER['HTTP_RANGE'] ?? '')) : '';

		if ($rangeHeader !== '')
		{
			$range = $this->ParseRange($rangeHeader, $fileSize);

			if ($range === null)
			{
				http_response_code(416);
				header('Content-Range: bytes */' . $fileSize);
				header('Content-Length: 0');
				exit;
			}

			$start = $range['start'];
			$end = $range['end'];
			http_response_code(206);
			header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
		}

		$length = $fileSize === 0 ? 0 : ($end - $start + 1);
		header('Content-Length: ' . $length);

		if ($length === 0)
		{
			exit;
		}

		$handle = fopen($path, 'rb');

		if ($handle === false)
		{
			throw new RuntimeException('Private file could not be opened.');
		}

		try
		{
			if ($start > 0 && fseek($handle, $start) !== 0)
			{
				throw new RuntimeException('Private file range could not be selected.');
			}

			$remaining = $length;

			while ($remaining > 0 && !feof($handle) && connection_status() === CONNECTION_NORMAL)
			{
				$chunk = fread($handle, min(self::CHUNK_BYTES, $remaining));

				if ($chunk === false)
				{
					throw new RuntimeException('Private file could not be read.');
				}

				if ($chunk === '')
				{
					break;
				}

				echo $chunk;
				$remaining -= strlen($chunk);
				flush();
			}
		}
		finally
		{
			fclose($handle);
		}

		exit;
	}

	/**
	 * @brief Parses one RFC 7233-style byte range.
	 * @return array{start:int,end:int}|null Parsed inclusive range, or null when unsatisfiable.
	 */
	public function ParseRange(string $header, int $fileSize): ?array
	{
		if ($fileSize <= 0 || str_contains($header, ',') || preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $match) !== 1)
		{
			return null;
		}

		$startText = (string)$match[1];
		$endText = (string)$match[2];

		if ($startText === '' && $endText === '')
		{
			return null;
		}

		if ($startText === '')
		{
			$suffixLength = (int)$endText;

			if ($suffixLength <= 0)
			{
				return null;
			}

			return [
				'start' => max(0, $fileSize - $suffixLength),
				'end' => $fileSize - 1,
			];
		}

		$start = (int)$startText;

		if ($start < 0 || $start >= $fileSize)
		{
			return null;
		}

		$end = $endText === '' ? $fileSize - 1 : min((int)$endText, $fileSize - 1);

		if ($end < $start)
		{
			return null;
		}

		return ['start' => $start, 'end' => $end];
	}

	/** @brief Allows a deliberately framed same-origin preview while retaining all other isolation. */
	public function AllowSameOriginFrame(): void
	{
		header("Content-Security-Policy: default-src 'self'; base-uri 'none'; connect-src 'none'; font-src 'self'; form-action 'none'; frame-ancestors 'self'; img-src 'self' data:; media-src 'self'; object-src 'none'; script-src 'none'; style-src 'self'");
		header('X-Frame-Options: SAMEORIGIN');
	}

	/** @brief Releases session/output locks before a potentially long response. */
	private function PrepareResponse(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE)
		{
			session_write_close();
		}

		@set_time_limit(0);

		while (ob_get_level() > 0)
		{
			ob_end_clean();
		}
	}

	/** @brief Emits common private-stream response headers. */
	private function SendHeaders(
		string $filename,
		string $contentType,
		string $disposition,
		bool $allowRanges,
		bool $allowSameOriginFrame
	): void
	{
		$asciiFilename = preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'document';
		$contentType = strtolower(trim($contentType));

		if (preg_match('/^[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+$/', $contentType) !== 1)
		{
			$contentType = 'application/octet-stream';
		}

		$disposition = $disposition === 'attachment' ? 'attachment' : 'inline';
		header('Content-Type: ' . $contentType);
		header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $asciiFilename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
		header('Cache-Control: no-store, private');
		header('Pragma: no-cache');
		header('X-Content-Type-Options: nosniff');
		header('X-Accel-Buffering: no');
		header('Accept-Ranges: ' . ($allowRanges ? 'bytes' : 'none'));

		if ($allowSameOriginFrame)
		{
			$this->AllowSameOriginFrame();
		}
	}
}
