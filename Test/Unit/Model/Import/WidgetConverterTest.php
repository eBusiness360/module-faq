<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Maxcode\Faq\Model\Import\WidgetConverter;
use Maxcode\Faq\Model\ResourceModel\Group as GroupResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WidgetConverterTest extends TestCase
{
    private GroupResource&MockObject $groups;
    private WidgetConverter $converter;

    protected function setUp(): void
    {
        $this->groups = $this->createMock(GroupResource::class);
        $this->groups->method('findIdBySource')->willReturnCallback(
            static fn (string $source, string $ref): ?int => $source === 'mageprince' ? ['1' => 11, '4' => 14][$ref] ?? null : null
        );
        $this->converter = new WidgetConverter($this->createMock(ResourceConnection::class), $this->groups);
    }

    /**
     * @return array{found:int,converted:int,lines:string[],warnings:string[]}
     */
    private function report(): array
    {
        return ['found' => 0, 'converted' => 0, 'lines' => [], 'warnings' => []];
    }

    public function testConvertsTheWidgetAsEnteredInTheEditor(): void
    {
        $report = $this->report();
        $changes = 0;
        $content = '<p>a</p>{{widget type="Mageprince\\Faq\\Block\\Index\\Index" template="Mageprince_Faq::faq_contact.phtml" group_id="1,4" show_group_title="1"}}<p>b</p>';

        $out = $this->converter->convertContent($content, $changes, $report, 'cms_block contact');

        $this->assertSame(
            '<p>a</p>{{widget type="Maxcode\\Faq\\Block\\Faq" group_ids="11,14" show_group_titles="1"}}<p>b</p>',
            $out
        );
        $this->assertSame(1, $changes);
        $this->assertSame(1, $report['converted']);
    }

    public function testConvertsTheWidgetStoredWithEscapedQuotesAndKeepsThatWriting(): void
    {
        $report = $this->report();
        $changes = 0;
        $content = '{{widget type=\\"Mageprince\\\\Faq\\\\Block\\\\Index\\\\Index\\" group_id=\\"1\\" show_group=\\"1\\" page_type=\\"scroll\\" show_group_title=\\"0\\" enable_collapse_expand=\\"1\\"}}';

        $out = $this->converter->convertContent($content, $changes, $report, 'cms_block map');

        // Même écriture que l'original (guillemets échappés, barres doublées), pour ne pas casser un JSON qui l'enrobe.
        $this->assertSame(
            '{{widget type=\\"Maxcode\\\\Faq\\\\Block\\\\Faq\\" group_ids=\\"11\\" show_group_titles=\\"0\\" expand_all=\\"1\\"}}',
            $out
        );
        $this->assertSame(
            '{{widget type="Maxcode\\Faq\\Block\\Faq" group_ids="11" show_group_titles="0" expand_all="1"}}',
            substr($report['lines'][0], strpos($report['lines'][0], '{{'))
        );
    }

    public function testDoubledBackslashesWithoutEscapedQuotesAreKept(): void
    {
        $report = $this->report();
        $changes = 0;
        $content = '{{widget type="Mageprince\\\\Faq\\\\Block\\\\Index\\\\Index" group_id="1"}}';

        $out = $this->converter->convertContent($content, $changes, $report, 'x');

        $this->assertSame('{{widget type="Maxcode\\\\Faq\\\\Block\\\\Faq" group_ids="11"}}', $out);
    }

    public function testADirectiveInsideJsonStaysValidJson(): void
    {
        $json = (string) json_encode(['content' => '{{widget type="Mageprince\\Faq\\Block\\Index\\Index" group_id="1"}}']);
        $report = $this->report();
        $changes = 0;

        $out = $this->converter->convertContent($json, $changes, $report, 'x');
        $decoded = json_decode($out, true);

        $this->assertIsArray($decoded);
        $this->assertSame('{{widget type="Maxcode\\Faq\\Block\\Faq" group_ids="11"}}', $decoded['content']);
    }

    public function testKeepsTheTitle(): void
    {
        $report = $this->report();
        $changes = 0;
        $out = $this->converter->convertContent(
            '{{widget type="Mageprince\\Faq\\Block\\Index\\Index" title="Nos réponses" group_id="1"}}',
            $changes,
            $report,
            'x'
        );

        $this->assertStringContainsString('group_ids="11"', $out);
        $this->assertStringContainsString('title="Nos réponses"', $out);
        $this->assertSame(1, $changes);
    }

    public function testLeavesTheWidgetAloneWhenAGroupWasNotImported(): void
    {
        $report = $this->report();
        $changes = 0;
        $content = '{{widget type="Mageprince\\Faq\\Block\\Index\\Index" group_id="1,99"}}';

        $out = $this->converter->convertContent($content, $changes, $report, 'cms_page home');

        $this->assertSame($content, $out);
        $this->assertSame(0, $changes);
        $this->assertSame(1, $report['found']);
        $this->assertStringContainsString('group #99', $report['warnings'][0]);
    }

    public function testLeavesOtherWidgetsAlone(): void
    {
        $report = $this->report();
        $changes = 0;
        $content = '{{widget type="Magento\\Cms\\Block\\Widget\\Block" block_id="3"}} {{block id="x"}}';

        $this->assertSame($content, $this->converter->convertContent($content, $changes, $report, 'x'));
        $this->assertSame(0, $report['found']);
    }

    public function testConvertingTwiceChangesNothingMore(): void
    {
        $report = $this->report();
        $changes = 0;
        $first = $this->converter->convertContent('{{widget type="Mageprince\\Faq\\Block\\Index\\Index" group_id="1"}}', $changes, $report, 'x');
        $changes = 0;
        $report = $this->report();
        $second = $this->converter->convertContent($first, $changes, $report, 'x');

        $this->assertSame($first, $second);
        $this->assertSame(0, $report['found']);
    }
}
