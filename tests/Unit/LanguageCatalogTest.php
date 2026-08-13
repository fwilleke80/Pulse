<?php

/**
 * @file LanguageCatalogTest.php
 * @brief Tests drop-in language discovery and metadata.
 * @author Frank Willeke
 */

declare(strict_types=1);

namespace Pulse\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pulse\Core\LanguageCatalog;

class LanguageCatalogTest extends TestCase
{
	private string $_directory;

	protected function setUp(): void
	{
		$this->_directory = sys_get_temp_dir() . '/pulse-languages-' . bin2hex(random_bytes(8));
		mkdir($this->_directory, 0700, true);
		$this->WriteLanguage('en', 'English', ['example' => 'English text']);
		$this->WriteLanguage('it', 'Italiano', ['example' => 'Testo italiano']);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->_directory . '/*') ?: [] as $file)
		{
			@unlink($file);
		}

		@rmdir($this->_directory);
	}

	public function testLanguageFilesAreDiscoveredWithoutConfigurationEntries(): void
	{
		$catalog = new LanguageCatalog($this->_directory, 'en');

		self::assertSame(['en', 'it'], $catalog->Locales());
		self::assertTrue($catalog->Has('it'));
		self::assertSame('Italiano', $catalog->Name('it'));
	}

	public function testMissingLanguageNameFallsBackToLocaleCode(): void
	{
		file_put_contents($this->_directory . '/fr.php', "<?php\nreturn ['example' => 'Texte'];\n");
		$catalog = new LanguageCatalog($this->_directory, 'en');

		self::assertSame('fr', $catalog->Name('fr'));
	}

	/**
	 * @param array<string, string> $strings Translation strings.
	 * @brief Writes one temporary language file.
	 */
	private function WriteLanguage(string $locale, string $name, array $strings): void
	{
		$payload = array_merge(['_language.name' => $name], $strings);
		file_put_contents(
			$this->_directory . '/' . $locale . '.php',
			"<?php\nreturn " . var_export($payload, true) . ";\n"
		);
	}
}
