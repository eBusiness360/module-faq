<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\ResourceModel\Question\Grid;

use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Source de la grille des questions : le texte par défaut (vue 0) et les titres des groupes, en lecture seule.
 * La table principale et le modèle de ressource sont déclarés en DI.
 */
class Collection extends SearchResult
{
    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()
            ->joinLeft(
                ['qt' => $this->getTable('maxcode_faq_question_text')],
                'qt.question_id = main_table.question_id AND qt.store_id = 0',
                ['question' => 'qt.question']
            )
            ->columns([
                'group_titles' => new Expression(
                    '(SELECT GROUP_CONCAT(gt.title ORDER BY g.sort_order, g.group_id SEPARATOR \', \')'
                    . ' FROM ' . $this->getTable('maxcode_faq_question_group') . ' qg'
                    . ' JOIN ' . $this->getTable('maxcode_faq_group') . ' g ON g.group_id = qg.group_id'
                    . ' JOIN ' . $this->getTable('maxcode_faq_group_text') . ' gt'
                    . ' ON gt.group_id = g.group_id AND gt.store_id = 0'
                    . ' WHERE qg.question_id = main_table.question_id)'
                ),
            ]);
        $this->addFilterToMap('question', 'qt.question');
        foreach (['question_id', 'status', 'sort_order', 'show_on_page', 'source', 'updated_at', 'created_at'] as $field) {
            $this->addFilterToMap($field, 'main_table.' . $field);
        }

        return $this;
    }
}
