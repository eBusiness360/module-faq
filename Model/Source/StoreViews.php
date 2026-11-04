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
 * Les vues de magasin, par site et boutique, avec « Toutes les vues » (0) en tête.
 */
class StoreViews implements OptionSourceInterface
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
        return $this->systemStore->getStoreValuesForForm(false, true);
    }

    /**
     * @return array<int, string> Vues de magasin seules (identifiant => nom), sans « Toutes les vues ».
     */
    public function toStoreList(): array
    {
        $list = [];
        foreach ($this->systemStore->getStoreCollection() as $store) {
            if ((int) $store->getId() > 0) {
                $list[(int) $store->getId()] = (string) $store->getName();
            }
        }

        return $list;
    }
}
