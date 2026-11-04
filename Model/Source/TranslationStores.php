<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\System\Store;

/**
 * Les vues de magasin à choisir pour une traduction (sans « Toutes les vues » : le texte par défaut est celui de la
 * question elle-même).
 */
class TranslationStores implements OptionSourceInterface
{
    /**
     * @param Store $systemStore Liste des sites, boutiques et vues de magasin.
     */
    public function __construct(private readonly Store $systemStore)
    {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return $this->systemStore->getStoreValuesForForm(false, false);
    }
}
