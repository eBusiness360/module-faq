<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Content;

use Maxcode\Faq\Model\Content\JsonLd;
use PHPUnit\Framework\TestCase;

class JsonLdTest extends TestCase
{
    public function testBuildsAFaqPage(): void
    {
        $json = (new JsonLd())->build([
            ['question' => 'Frais de port ?', 'answer' => '<p>Gratuits dès 99 €.</p>'],
        ]);
        $data = json_decode($json, true);

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('FAQPage', $data['@type']);
        $this->assertSame('Question', $data['mainEntity'][0]['@type']);
        $this->assertSame('Frais de port ?', $data['mainEntity'][0]['name']);
        $this->assertSame('Answer', $data['mainEntity'][0]['acceptedAnswer']['@type']);
        $this->assertSame('<p>Gratuits dès 99 €.</p>', $data['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function testNothingToDescribeGivesAnEmptyString(): void
    {
        $this->assertSame('', (new JsonLd())->build([]));
        $this->assertSame('', (new JsonLd())->build([['question' => ' ', 'answer' => '<p>x</p>'], ['question' => 'q', 'answer' => '']]));
    }

    public function testCannotBreakOutOfTheScriptTag(): void
    {
        $json = (new JsonLd())->build([
            ['question' => 'Piège </script><script>alert(1)</script> ?', 'answer' => '<p>a</p></script><b>x</b>'],
        ]);

        $this->assertStringNotContainsString('</script', strtolower($json));
        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString('>', $json);
        $entity = json_decode($json, true)['mainEntity'][0];
        // La question est du texte brut (balises retirées) ; la réponse est conservée telle quelle, mais encodée.
        $this->assertStringNotContainsString('<', $entity['name']);
        $this->assertSame('<p>a</p></script><b>x</b>', $entity['acceptedAnswer']['text']);
    }

    public function testQuestionTextIsPlain(): void
    {
        $json = (new JsonLd())->build([['question' => "<b>Gras</b>  et\nlignes ?", 'answer' => '<p>r</p>']]);

        $this->assertSame('Gras et lignes ?', json_decode($json, true)['mainEntity'][0]['name']);
    }

    public function testOnlyTheFirstBlockOfARequestMayWriteTheMarkup(): void
    {
        $jsonLd = new JsonLd();

        $this->assertTrue($jsonLd->claim());
        $this->assertFalse($jsonLd->claim());
    }
}
