<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Table maxcode_faq_group et ses titres par vue de magasin (maxcode_faq_group_text).
 */
class Group extends AbstractDb
{
    public const TABLE = 'maxcode_faq_group';
    public const TABLE_TEXT = 'maxcode_faq_group_text';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'group_id');
    }

    /**
     * @inheritdoc
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $texts = $object->getData('texts');
        if (is_array($texts)) {
            if (trim((string) ($texts[0] ?? '')) === '') {
                throw new LocalizedException(__('The group needs a title.'));
            }
        } elseif (!$object->getId()) {
            throw new LocalizedException(__('The group needs a title.'));
        }

        return parent::_beforeSave($object);
    }

    /**
     * @inheritdoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        $texts = $object->getData('texts');
        if (is_array($texts)) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::TABLE_TEXT);
            $id = (int) $object->getId();
            $rows = [];
            foreach ($texts as $storeId => $title) {
                $title = trim((string) $title);
                if ($title !== '' || (int) $storeId === 0) {
                    $rows[(int) $storeId] = ['group_id' => $id, 'store_id' => (int) $storeId, 'title' => $title];
                }
            }
            $keep = array_keys($rows);
            $connection->delete($table, ['group_id = ?' => $id, 'store_id NOT IN (?)' => $keep ?: [0]]);
            if ($rows) {
                $connection->insertOnDuplicate($table, array_values($rows), ['title']);
            }
        }

        return parent::_afterSave($object);
    }

    /**
     * @inheritdoc
     */
    protected function _afterLoad(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($this->getTable(self::TABLE_TEXT), ['store_id', 'title'])
                ->where('group_id = ?', (int) $object->getId())
        );
        $texts = [];
        foreach ($rows as $storeId => $title) {
            $texts[(int) $storeId] = (string) $title;
        }
        $object->setData('texts', $texts);

        return parent::_afterLoad($object);
    }

    /**
     * Identifiant d'un groupe importé, d'après son origine.
     *
     * @param string $source Origine (« mageprince », « mageworx »...).
     * @param string $ref    Identifiant dans l'origine.
     * @return int|null
     */
    public function findIdBySource(string $source, string $ref): ?int
    {
        $connection = $this->getConnection();
        $id = $connection->fetchOne(
            $connection->select()->from($this->getMainTable(), ['group_id'])
                ->where('source = ?', $source)->where('source_ref = ?', $ref)
        );

        return $id === false ? null : (int) $id;
    }
}
