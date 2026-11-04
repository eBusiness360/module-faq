<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Source;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Les groupes de la FAQ (titre par défaut), pour les listes de l'administration et le widget.
 */
class GroupOptions implements OptionSourceInterface
{
    /**
     * @param ResourceConnection $resource Connexion à la base.
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['g' => $this->resource->getTableName('maxcode_faq_group')], ['group_id', 'is_active'])
                ->joinLeft(
                    ['t' => $this->resource->getTableName('maxcode_faq_group_text')],
                    't.group_id = g.group_id AND t.store_id = 0',
                    ['title']
                )
                ->order(['g.sort_order ASC', 'g.group_id ASC'])
        );
        $options = [];
        foreach ($rows as $row) {
            $label = trim((string) $row['title']) ?: '#' . $row['group_id'];
            $options[] = [
                'value' => (int) $row['group_id'],
                'label' => (int) $row['is_active'] === 1 ? $label : $label . ' (' . __('inactive') . ')',
            ];
        }

        return $options;
    }
}
