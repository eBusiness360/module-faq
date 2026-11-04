<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Maxcode\Faq\Model\ResourceModel\Group as GroupResource;

/**
 * Un groupe de questions (une « catégorie » de la FAQ). Clé de données « texts » : vue de magasin => titre ; le titre
 * de la vue 0 est le titre par défaut.
 */
class Group extends AbstractModel implements IdentityInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'maxcode_faq_group';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(GroupResource::class);
    }

    /**
     * @return string[]
     */
    public function getIdentities(): array
    {
        return [Question::CACHE_TAG, Question::CACHE_TAG . '_g_' . (int) $this->getId()];
    }
}
