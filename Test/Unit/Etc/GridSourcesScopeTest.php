<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

/**
 * Les sources des grilles (argument « collections » de UiComponent CollectionFactory) doivent être déclarées en portée
 * globale. Déclarées dans etc/adminhtml/di.xml, elles REMPLACENT le tableau global (ObjectManager Config::extend fait un
 * array_replace par argument) : toutes les autres grilles de l'admin tombent en « Not registered handle ».
 */
class GridSourcesScopeTest extends TestCase
{
    private const TYPE = 'Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory';

    public function testNoAreaDiFileOverridesTheGridSources(): void
    {
        foreach (glob(dirname(__DIR__, 3) . '/etc/*/di.xml') ?: [] as $file) {
            $this->assertStringNotContainsString(
                self::TYPE,
                (string)file_get_contents($file),
                basename(dirname($file)) . '/di.xml ne doit pas déclarer les sources des grilles.'
            );
        }
    }

    public function testTheTwoGridSourcesAreDeclaredGlobally(): void
    {
        $xml = (string)file_get_contents(dirname(__DIR__, 3) . '/etc/di.xml');

        $this->assertStringContainsString(self::TYPE, $xml);
        $this->assertStringContainsString('<item name="maxcode_faq_question_listing_data_source"', $xml);
        $this->assertStringContainsString('<item name="maxcode_faq_group_listing_data_source"', $xml);
    }
}
