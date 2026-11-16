<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Test\Unit\I18n;

use PHPUnit\Framework\TestCase;

class TranslationFilesTest extends TestCase
{
    private const I18N_DIR = __DIR__ . '/../../../i18n/';

    public function testEnglishFileMapsEveryPhraseToItself(): void
    {
        foreach ($this->read('en_US') as $phrase => $translation) {
            self::assertSame($phrase, $translation);
        }
    }

    public function testDutchFileTranslatesExactlyTheSamePhrases(): void
    {
        self::assertSame(array_keys($this->read('en_US')), array_keys($this->read('nl_NL')));
    }

    public function testDutchTranslationsKeepThePlaceholders(): void
    {
        foreach ($this->read('nl_NL') as $phrase => $translation) {
            self::assertNotSame('', $translation, $phrase);
            self::assertSame(
                substr_count($phrase, '%1'),
                substr_count($translation, '%1'),
                'Placeholder mismatch for: ' . $phrase
            );
        }
    }

    /**
     * @return array<string, string> Translation keyed by source phrase.
     */
    private function read(string $locale): array
    {
        $rows = [];
        $handle = fopen(self::I18N_DIR . $locale . '.csv', 'rb');
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            self::assertCount(2, $row, 'Each row needs a phrase and a translation.');
            self::assertArrayNotHasKey($row[0], $rows, 'Duplicate phrase: ' . $row[0]);
            $rows[$row[0]] = $row[1];
        }
        fclose($handle);

        self::assertNotEmpty($rows);

        return $rows;
    }
}
