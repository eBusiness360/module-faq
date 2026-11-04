<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Source;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Les pages CMS (valeur : numéro de la page), pour les affectations.
 */
class CmsPages implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory Pages CMS.
     */
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->collectionFactory->create()->setOrder('title', 'ASC') as $page) {
            $options[] = [
                'value' => (int) $page->getId(),
                'label' => (string) $page->getTitle() . ' (' . (string) $page->getIdentifier() . ')',
            ];
        }

        return $options;
    }
}
