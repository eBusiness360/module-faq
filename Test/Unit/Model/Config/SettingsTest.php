<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Maxcode\Faq\Model\Config\Backend\UrlKey;
use Maxcode\Faq\Model\Config\Settings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    private ScopeConfigInterface&MockObject $config;
    private Settings $settings;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ScopeConfigInterface::class);
        $this->settings = new Settings($this->config);
    }

    public function testFlagsAreReadWithTheStoreScope(): void
    {
        $this->config->expects($this->once())->method('isSetFlag')
            ->with(Settings::PATH_ENABLED, ScopeInterface::SCOPE_STORE, 3)->willReturn(true);

        $this->assertTrue($this->settings->isEnabled(3));
    }

    public function testUrlKeyIsTrimmedOfSlashes(): void
    {
        $this->config->method('getValue')->with(Settings::PATH_URL_KEY, ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('/aide/faq/');

        $this->assertSame('aide/faq', $this->settings->getUrlKey(1));
    }

    public function testMetaTitleFallsBackToThePageTitle(): void
    {
        $this->config->method('getValue')->willReturnMap([
            [Settings::PATH_META_TITLE, ScopeInterface::SCOPE_STORE, 1, ''],
            [Settings::PATH_PAGE_TITLE, ScopeInterface::SCOPE_STORE, 1, 'Aide'],
        ]);

        $this->assertSame('Aide', $this->settings->getMetaTitle(1));
    }

    public function testEmptyTextsStayEmpty(): void
    {
        $this->config->method('getValue')->willReturn('  ');

        $this->assertSame('', $this->settings->getMetaDescription(1));
    }

    /**
     * @dataProvider validKeys
     */
    public function testUrlKeyNormalization(string $input, string $expected): void
    {
        $this->assertSame($expected, UrlKey::normalize($input));
    }

    /**
     * @return array<string, string[]>
     */
    public static function validKeys(): array
    {
        return [
            'simple' => ['faq', 'faq'],
            'upper case and slashes' => ['/Aide/FAQ/', 'aide/faq'],
            'digits and underscore' => ['faq_2024', 'faq_2024'],
            'empty means no page' => ['  ', ''],
        ];
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testInvalidUrlKeysAreRefused(string $input): void
    {
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);

        UrlKey::normalize($input);
    }

    /**
     * @return array<string, string[]>
     */
    public static function invalidKeys(): array
    {
        return [
            'space' => ['ma faq'],
            'double slash' => ['aide//faq'],
            'dot' => ['faq.html'],
            'query' => ['faq?x=1'],
            'reserved' => ['checkout'],
            'reserved first segment' => ['admin/faq'],
            'too long' => [str_repeat('a', 101)],
        ];
    }
}
