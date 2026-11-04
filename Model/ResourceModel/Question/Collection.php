<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\ResourceModel\Question;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Maxcode\Faq\Model\Question;
use Maxcode\Faq\Model\ResourceModel\Question as QuestionResource;

/**
 * Questions (sans leurs listes liées : elles sont chargées avec le modèle).
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'question_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(Question::class, QuestionResource::class);
    }
}
