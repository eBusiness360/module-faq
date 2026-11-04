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
use Maxcode\Faq\Model\Question;

/**
 * Statut d'une question : active (affichée) ou brouillon (jamais affichée).
 */
class Status implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Question::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => Question::STATUS_DRAFT, 'label' => __('Draft')],
        ];
    }
}
