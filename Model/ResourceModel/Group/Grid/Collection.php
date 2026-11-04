<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\ResourceModel\Group\Grid;

use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Source de la grille des groupes : le titre par défaut (vue 0) et le nombre de questions.
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
                ['gt' => $this->getTable('maxcode_faq_group_text')],
                'gt.group_id = main_table.group_id AND gt.store_id = 0',
                ['title' => 'gt.title']
            )
            ->columns([
                'question_count' => new Expression(
                    '(SELECT COUNT(*) FROM ' . $this->getTable('maxcode_faq_question_group')
                    . ' qg WHERE qg.group_id = main_table.group_id)'
                ),
            ]);
        $this->addFilterToMap('title', 'gt.title');
        foreach (['group_id', 'sort_order', 'is_active', 'source', 'updated_at'] as $field) {
            $this->addFilterToMap($field, 'main_table.' . $field);
        }

        return $this;
    }
}
