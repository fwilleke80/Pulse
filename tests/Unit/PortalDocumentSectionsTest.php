<?php
/** @brief Checks portal grouping without changing stored order or snapshot data. */
declare(strict_types=1);
namespace Pulse\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Pulse\Services\PortalDocumentSections;
final class PortalDocumentSectionsTest extends TestCase
{
	/** @brief Sections follow the agreed order; titles use case-insensitive natural ordering. */
	public function testGrouping(): void
	{
		$items = [
			['id' => 1, 'title' => 'zebra', 'preview_kind' => 'image'],
			['id' => 2, 'title' => 'alpha', 'preview_kind' => 'pdf'],
			['id' => 3, 'title' => 'Note 10', 'storage_type' => 'text'],
			['id' => 4, 'title' => 'note 2', 'storage_type' => 'text'],
			['id' => 5, 'title' => 'Alpha', 'mime_type' => 'image/tiff'],
			['id' => 6, 'title' => 'Data', 'preview_kind' => 'csv'],
		];
		$groups = PortalDocumentSections::Group($items);
		self::assertSame(['text', 'pdf', 'image', 'other'], array_keys($groups));
		self::assertSame([4, 3], array_column($groups['text'], 'id'));
		self::assertSame([5, 1], array_column($groups['image'], 'id'));
		self::assertSame('zebra', $items[0]['title']);
		self::assertSame([], PortalDocumentSections::Group([]));
	}
}
