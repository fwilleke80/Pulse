<?php

/**
 * @file TranslatorTest.php
 * @brief Tests translation fallback for partially translated drop-in locales.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\Translator;

class TranslatorTest extends TestCase
{
	private string $_directory;

	protected function setUp(): void
	{
		$this->_directory = sys_get_temp_dir() . '/pulse-translator-' . bin2hex(random_bytes(8));
		mkdir($this->_directory, 0700, true);
		file_put_contents($this->_directory . '/en.php', "<?php\nreturn ['present' => 'English', 'fallback' => 'English fallback'];\n");
		file_put_contents($this->_directory . '/it.php', "<?php\nreturn ['present' => 'Italiano'];\n");
	}

	protected function tearDown(): void
	{
		foreach (glob($this->_directory . '/*') ?: [] as $file)
		{
			@unlink($file);
		}

		@rmdir($this->_directory);
	}

	public function testMissingItalianKeyFallsBackToEnglish(): void
	{
		$translator = new Translator($this->_directory, 'it', 'en');

		self::assertSame('Italiano', $translator->Translate('present'));
		self::assertSame('English fallback', $translator->Translate('fallback'));
		self::assertSame('unknown', $translator->Translate('unknown'));
	}
}
