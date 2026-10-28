<?php
/** @file PortalDocumentSections.php @brief Shared portal grouping and stable title ordering. */
declare(strict_types=1);
namespace Pulse\Services;
final class PortalDocumentSections
{
	/** @brief Groups prepared live or snapshot documents, omitting empty sections. @return array<string,array> */
	public static function Group(array $documents): array
	{
		$groups = array_fill_keys(['text', 'pdf', 'image', 'video', 'audio', 'other'], []);
		foreach ($documents as $document)
		{
			$kind = (string)($document['preview_kind'] ?? '');
			$mime = strtolower((string)($document['mime_type'] ?? $document['file_mime_type'] ?? ''));
			$group = match (true)
			{
				($document['storage_type'] ?? '') === 'text', in_array($kind, ['text', 'markdown'], true) => 'text',
				$kind === 'pdf', $mime === 'application/pdf' => 'pdf',
				$kind === 'image', str_starts_with($mime, 'image/') => 'image',
				$kind === 'video', str_starts_with($mime, 'video/') => 'video',
				$kind === 'audio', str_starts_with($mime, 'audio/') => 'audio',
				default => 'other',
			};
			$groups[$group][] = $document;
		}
		foreach ($groups as &$items)
		{
			usort($items, static fn (array $a, array $b): int => strnatcasecmp(mb_strtolower((string)$a['title']), mb_strtolower((string)$b['title'])) ?: ((int)$a['id'] <=> (int)$b['id']));
		}
		unset($items);
		return array_filter($groups, static fn (array $items): bool => $items !== []);
	}
}
