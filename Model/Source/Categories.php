<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Les catégories du catalogue à plat, avec leur chemin (« Serveurs > Serveurs GPU »), pour les affectations.
 */
class Categories implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory Catégories.
     */
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create()
            ->setStoreId(0)
            ->addAttributeToSelect('name')
            ->addFieldToFilter('level', ['gteq' => 1])
            ->setOrder('path', 'ASC');
        $names = [];
        $levels = [];
        foreach ($collection as $category) {
            $names[(int) $category->getId()] = (string) $category->getName();
            $levels[(int) $category->getId()] = (int) $category->getLevel();
        }
        $options = [];
        foreach ($collection as $category) {
            // Le niveau 1 est la racine d'une boutique : on la montre comme début du chemin, pas comme choix.
            if ($levels[(int) $category->getId()] < 2) {
                continue;
            }
            $labels = [];
            foreach (explode('/', (string) $category->getPath()) as $id) {
                if (isset($names[(int) $id]) && $levels[(int) $id] >= 1) {
                    $labels[] = $names[(int) $id];
                }
            }
            $options[] = ['value' => (int) $category->getId(), 'label' => implode(' > ', $labels)];
        }

        return $options;
    }
}
