<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Content;

use Magento\Cms\Model\Template\Filter;
use Magento\Cms\Model\Template\FilterProvider;
use Maxcode\Faq\Model\Content\AnswerRenderer;
use Maxcode\Faq\Model\Content\HtmlSanitizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AnswerRendererTest extends TestCase
{
    private Filter&MockObject $filter;
    private AnswerRenderer $renderer;

    protected function setUp(): void
    {
        $this->filter = $this->createMock(Filter::class);
        $this->filter->method('setStoreId')->willReturnSelf();
        $provider = $this->createMock(FilterProvider::class);
        $provider->method('getBlockFilter')->willReturn($this->filter);
        $this->renderer = new AnswerRenderer($provider, new HtmlSanitizer());
    }

    public function testPlainAnswerIsJustSanitizedAndNeverFiltered(): void
    {
        $this->filter->expects($this->never())->method('filter');

        $this->assertSame('<p>Bonjour</p>', $this->renderer->render('<p>Bonjour</p><script>x()</script>', 1));
    }

    public function testCategoryLinkWidgetIsKeptAndFiltered(): void
    {
        $directive = '{{widget type="Magento\\Catalog\\Block\\Category\\Widget\\Link" id_path="category/3"}}';
        $this->filter->expects($this->once())->method('filter')
            ->with('<p>Voir ' . $directive . '</p>')
            ->willReturn('<p>Voir <a href="https://shop.test/cat.html">Cat</a></p>');

        $this->assertSame(
            '<p>Voir <a href="https://shop.test/cat.html">Cat</a></p>',
            $this->renderer->render('<p>Voir ' . $directive . '</p>', 1)
        );
    }

    public function testEscapedQuotesAndDoubledBackslashesAreNormalizedBeforeFiltering(): void
    {
        $stored = '{{widget type=\\"Magento\\\\Cms\\\\Block\\\\Widget\\\\Page\\\\Link\\" page_id=\\"16\\"}}';
        $this->filter->expects($this->once())->method('filter')
            ->with('{{widget type="Magento\\Cms\\Block\\Widget\\Page\\Link" page_id="16"}}')
            ->willReturn('<a href="/p">P</a>');

        $this->assertSame('<a href="/p">P</a>', $this->renderer->render($stored, 1));
    }

    public function testOtherDirectivesAreRemoved(): void
    {
        $this->filter->expects($this->never())->method('filter');
        $html = 'a{{config path="web/secure/base_url"}}b{{block class="Magento\\Cms\\Block\\Block" block_id="x"}}c'
            . '{{widget type="Magento\\Cms\\Block\\Widget\\Block" block_id="1"}}d{{var customer.password}}e'
            . '{{if x}}f{{/if}}';

        $this->assertSame('abcdef', $this->renderer->render($html, 1));
    }

    /**
     * @dataProvider bypassAttempts
     */
    public function testWidgetDirectivesThatTryToSmuggleAnotherWidgetAreRemoved(string $directive): void
    {
        $this->filter->expects($this->never())->method('filter');

        $this->assertSame('ab', $this->renderer->render('a' . $directive . 'b', 1));
    }

    /**
     * @return array<string, string[]>
     */
    public static function bypassAttempts(): array
    {
        $link = 'Magento\\Cms\\Block\\Widget\\Page\\Link';
        $block = 'Magento\\Cms\\Block\\Widget\\Block';

        return [
            'second type' => ['{{widget type="' . $link . '" type="' . $block . '" block_id="5"}}'],
            'type hidden in a title' => ['{{widget title="x type=\'' . $link . '\'" type="' . $block . '"}}'],
            'block id parameter' => ['{{widget type="' . $link . '" block_id="5"}}'],
            'foreign template' => ['{{widget type="' . $link . '" page_id="1" template="widget/static_block/default.phtml"}}'],
            'single quotes' => ["{{widget type='" . $link . "' page_id='1'}}"],
            'spaces around the equal sign' => ['{{widget type = "' . $link . '" page_id="1"}}'],
            'upper-case parameter name' => ['{{widget TYPE="' . $block . '" type="' . $link . '"}}'],
            'no type' => ['{{widget page_id="1"}}'],
        ];
    }

    public function testAllowedInlineTemplateIsKept(): void
    {
        $directive = '{{widget type="Magento\\Catalog\\Block\\Category\\Widget\\Link" id_path="category/3" '
            . 'template="category/widget/link/link_inline.phtml"}}';
        $this->filter->expects($this->once())->method('filter')->with($directive)->willReturn('<a href="/c">C</a>');

        $this->assertSame('<a href="/c">C</a>', $this->renderer->render($directive, 1));
    }

    public function testMediaDirectiveIsAllowed(): void
    {
        $this->filter->expects($this->once())->method('filter')
            ->willReturn('<img src="https://shop.test/media/a.png">');

        $this->assertStringContainsString(
            'src="https://shop.test/media/a.png"',
            $this->renderer->render('{{media url="wysiwyg/a.png"}}', 1)
        );
    }

    public function testFilterFailureFallsBackToTextWithoutDirectives(): void
    {
        $this->filter->method('filter')->willThrowException(new \RuntimeException('boom'));
        $directive = '{{widget type="Magento\\Catalog\\Block\\Category\\Widget\\Link" id_path="category/3"}}';

        $this->assertSame('<p>texte</p>', $this->renderer->render('<p>texte' . $directive . '</p>', 1));
    }

    public function testHtmlProducedByTheFilterIsSanitizedToo(): void
    {
        $this->filter->method('filter')->willReturn('<a href="javascript:x()" onclick="y()">piège</a>');
        $directive = '{{widget type="Magento\\Catalog\\Block\\Category\\Widget\\Link" id_path="category/3"}}';

        $this->assertSame('piège', $this->renderer->render($directive, 1));
    }

    public function testMagezonPageBuilderMarkupIsReducedToItsTexts(): void
    {
        $json = '{"elements":[{"type":"row","elements":[{"type":"column","elements":['
            . '{"type":"text","content":"<p>Premier</p>"},{"type":"text","content":"<p>Second</p>"}]}]}],"pid":"x"}';

        $this->filter->expects($this->never())->method('filter');
        $this->assertSame(
            "<p>Premier</p>\n<p>Second</p>",
            $this->renderer->render('[mgz_pagebuilder]' . $json . '[/mgz_pagebuilder]', 1)
        );
    }

    public function testBrokenPageBuilderMarkupDisappears(): void
    {
        $this->assertSame('<p>ok</p>', $this->renderer->render('<p>ok</p>[mgz_pagebuilder]{pas du json[/mgz_pagebuilder]', 1));
    }
}
