<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Admin;

use Magento\Framework\App\ResourceConnection;
use Maxcode\Faq\Model\Admin\ProductSkus;
use PHPUnit\Framework\TestCase;

class ProductSkusTest extends TestCase
{
    private ProductSkus $skus;

    protected function setUp(): void
    {
        $this->skus = new ProductSkus($this->createMock(ResourceConnection::class));
    }

    public function testParseSplitsOnLinesCommasAndSemicolons(): void
    {
        $this->assertSame(['A1', 'B-2', 'C3', 'D4'], $this->skus->parse("A1\r\nB-2, C3;;\n  \nD4"));
    }

    public function testParseDropsDuplicatesIgnoringCase(): void
    {
        $this->assertSame(['ab', 'CD'], $this->skus->parse("ab\nAB\nCD\ncd"));
    }

    public function testParseIgnoresAbsurdlyLongValuesAndCapsTheList(): void
    {
        $this->assertSame([], $this->skus->parse(str_repeat('x', 65)));
        $many = implode("\n", array_map(static fn (int $i): string => 'S' . $i, range(1, 800)));
        $this->assertCount(ProductSkus::MAX_SKUS, $this->skus->parse($many));
    }

    public function testResolveWithNothingDoesNotTouchTheDatabase(): void
    {
        $this->assertSame(['ids' => [], 'unknown' => []], $this->skus->resolve([]));
        $this->assertSame('', $this->skus->toText([]));
    }
}
