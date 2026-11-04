<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Content;

use Maxcode\Faq\Model\Content\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testKeepsSimpleFormatting(): void
    {
        $html = '<p>Un <strong>gras</strong> et <em>italique</em>.</p><ul><li>un</li><li>deux</li></ul>';

        $this->assertSame($html, $this->sanitizer->sanitize($html));
    }

    public function testKeepsAccentsAndEntities(): void
    {
        $this->assertSame('<p>Délai à 48 h &amp; plus</p>', $this->sanitizer->sanitize('<p>Délai à 48 h &amp; plus</p>'));
    }

    public function testRemovesScriptsWithTheirContent(): void
    {
        $out = $this->sanitizer->sanitize('<p>a</p><script>alert(1)</script><style>p{}</style><iframe src="x"></iframe>');

        $this->assertSame('<p>a</p>', $out);
    }

    public function testRemovesEventHandlersAndStyles(): void
    {
        $out = $this->sanitizer->sanitize('<p onclick="x()" style="color:red" class="c" id="i">t</p>');

        $this->assertSame('<p>t</p>', $out);
    }

    /**
     * @dataProvider dangerousUrls
     */
    public function testRefusesDangerousLinkAddresses(string $href): void
    {
        $out = $this->sanitizer->sanitize('<a href="' . $href . '">texte</a>');

        $this->assertStringNotContainsStringIgnoringCase('script:', $out);
        $this->assertStringNotContainsString('href', $out);
        $this->assertStringContainsString('texte', $out);
    }

    /**
     * @return array<string, string[]>
     */
    public static function dangerousUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'mixed case' => ['JaVaScRiPt:alert(1)'],
            'tab inside' => ["java\tscript:alert(1)"],
            'newline inside' => ["java\nscript:alert(1)"],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript' => ['vbscript:x'],
        ];
    }

    public function testKeepsSafeLinks(): void
    {
        $out = $this->sanitizer->sanitize(
            '<a href="https://example.com/a?b=1">a</a><a href="/page.html">b</a><a href="mailto:x@y.fr">c</a><a href="tel:+33102030405">d</a>'
        );

        $this->assertStringContainsString('href="https://example.com/a?b=1"', $out);
        $this->assertStringContainsString('href="/page.html"', $out);
        $this->assertStringContainsString('href="mailto:x@y.fr"', $out);
        $this->assertStringContainsString('href="tel:+33102030405"', $out);
    }

    public function testBlankTargetGetsNoopener(): void
    {
        $out = $this->sanitizer->sanitize('<a href="https://example.com" target="_blank">x</a>');

        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    public function testUnknownTargetIsDropped(): void
    {
        $out = $this->sanitizer->sanitize('<a href="https://example.com" target="evil" rel="x">x</a>');

        $this->assertStringNotContainsString('target', $out);
        $this->assertStringNotContainsString('rel=', $out);
    }

    public function testLinkWithoutAddressKeepsItsContent(): void
    {
        $out = $this->sanitizer->sanitize('<p><a>texte seul</a></p>');

        $this->assertSame('<p>texte seul</p>', $out);
    }

    public function testImageNeedsHttpOrRelativeSource(): void
    {
        $this->assertStringContainsString(
            'src="https://example.com/a.png"',
            $this->sanitizer->sanitize('<img src="https://example.com/a.png" alt="x" onerror="x()">')
        );
        $this->assertStringNotContainsString('onerror', $this->sanitizer->sanitize('<img src="/a.png" onerror="x()">'));
        $this->assertSame('', $this->sanitizer->sanitize('<img src="javascript:alert(1)">'));
        $this->assertSame('', $this->sanitizer->sanitize('<img src="data:image/svg+xml;base64,AAA=">'));
    }

    public function testUnknownTagsAreUnwrappedButTheirTextIsKept(): void
    {
        $this->assertSame('<p>un deux</p>', $this->sanitizer->sanitize('<p><custom-tag>un</custom-tag> <font>deux</font></p>'));
    }

    public function testFormsAndInputsAreRemoved(): void
    {
        $out = $this->sanitizer->sanitize('<form action="/x"><input name="a"><button>go</button></form><p>ok</p>');

        $this->assertSame('<p>ok</p>', $out);
    }

    public function testCommentsAndSvgAreRemoved(): void
    {
        $out = $this->sanitizer->sanitize('<!-- note --><svg onload="x()"><script>1</script></svg><p>ok</p>');

        $this->assertSame('<p>ok</p>', $out);
    }

    public function testLevelOneAndTwoHeadingsAreFlattenedButLowerOnesKept(): void
    {
        $out = $this->sanitizer->sanitize('<h1>Un</h1><h2>Deux</h2><h3>Trois</h3>');

        $this->assertSame('UnDeux<h3>Trois</h3>', $out);
    }

    public function testAStrayClosingTagDoesNotCutWhatFollows(): void
    {
        $this->assertSame('ab', $this->sanitizer->sanitize('a</div>b'));
        $this->assertSame('<p>a</p><p>b</p>', $this->sanitizer->sanitize('<p>a</p></div></span><p>b</p>'));
    }

    public function testEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
        $this->assertSame('', $this->sanitizer->sanitize("  \n "));
    }

    public function testPlainTextStaysText(): void
    {
        $this->assertSame('Juste du texte', $this->sanitizer->sanitize('Juste du texte'));
    }

    public function testTableCellAttributesAreFiltered(): void
    {
        $out = $this->sanitizer->sanitize('<table><tr><td colspan="2" onclick="x()" style="a:b">c</td></tr></table>');

        $this->assertStringContainsString('colspan="2"', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('style', $out);
    }
}
