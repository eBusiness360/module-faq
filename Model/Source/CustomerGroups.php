<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Source;

use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Les groupes de clients, « non connecté » compris.
 */
class CustomerGroups implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory Groupes de clients.
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
        foreach ($this->collectionFactory->create() as $group) {
            $options[] = ['value' => (int) $group->getId(), 'label' => (string) $group->getCode()];
        }

        return $options;
    }
}
