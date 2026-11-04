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
use Maxcode\Faq\Model\Question as QuestionModel;

/**
 * Table maxcode_faq_question et ses listes liées : textes par vue de magasin, vues de magasin où la question est
 * visible, groupes, groupes de clients et affectations (produits, catégories, pages CMS).
 */
class Question extends AbstractDb
{
    public const TABLE = 'maxcode_faq_question';
    public const TABLE_TEXT = 'maxcode_faq_question_text';
    public const TABLE_STORE = 'maxcode_faq_question_store';
    public const TABLE_GROUP = 'maxcode_faq_question_group';
    public const TABLE_CUSTOMER_GROUP = 'maxcode_faq_question_customer_group';
    public const TABLE_ASSIGNMENT = 'maxcode_faq_assignment';
    public const ENTITY_TYPES = ['product', 'category', 'cms_page'];

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'question_id');
    }

    /**
     * @inheritdoc
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $texts = $object->getData('texts');
        if (is_array($texts)) {
            $default = $texts[0] ?? [];
            if (trim((string) ($default['question'] ?? '')) === '') {
                throw new LocalizedException(__('The question needs a text.'));
            }
        } elseif (!$object->getId()) {
            throw new LocalizedException(__('The question needs a text.'));
        }
        // Une question active doit avoir une réponse dans chacune de ses langues. Quand les textes ne sont pas dans
        // les données (changement de statut seul, ligne venue d'une grille), on les lit en base : le contrôle ne se
        // contourne pas par un enregistrement partiel.
        if ((int) $object->getData('status') === QuestionModel::STATUS_ACTIVE) {
            $check = is_array($texts) ? $texts : $this->loadTexts((int) $object->getId());
            foreach ($check as $text) {
                if (trim((string) ($text['question'] ?? '')) !== '' && trim((string) ($text['answer'] ?? '')) === '') {
                    throw new LocalizedException(__('An active question needs an answer in every language it has.'));
                }
            }
        }

        return parent::_beforeSave($object);
    }

    /**
     * @inheritdoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        $id = (int) $object->getId();
        $texts = $object->getData('texts');
        if (is_array($texts)) {
            $this->saveTexts($id, $texts);
        }
        if (is_array($object->getData('store_ids'))) {
            $stores = $this->ids($object->getData('store_ids'));
            $this->replaceLinks(self::TABLE_STORE, 'store_id', $id, $stores ?: [0]);
        } elseif ($object->isObjectNew()) {
            $this->replaceLinks(self::TABLE_STORE, 'store_id', $id, [0]);
        }
        if (is_array($object->getData('group_ids'))) {
            $this->replaceLinks(self::TABLE_GROUP, 'group_id', $id, $this->ids($object->getData('group_ids')));
        }
        if (is_array($object->getData('customer_group_ids'))) {
            $this->replaceLinks(
                self::TABLE_CUSTOMER_GROUP,
                'customer_group_id',
                $id,
                $this->ids($object->getData('customer_group_ids'))
            );
        }
        if (is_array($object->getData('assignments'))) {
            $object->setData('previous_assignments', $this->loadAssignments($id));
            $this->saveAssignments($id, $object->getData('assignments'));
        }

        return parent::_afterSave($object);
    }

    /**
     * @inheritdoc
     */
    protected function _afterLoad(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $id = (int) $object->getId();
        $object->setData('texts', $this->loadTexts($id));
        $object->setData('store_ids', $this->loadLinks(self::TABLE_STORE, 'store_id', $id));
        $object->setData('group_ids', $this->loadLinks(self::TABLE_GROUP, 'group_id', $id));
        $object->setData(
            'customer_group_ids',
            $this->loadLinks(self::TABLE_CUSTOMER_GROUP, 'customer_group_id', $id)
        );
        $object->setData('assignments', $this->loadAssignments($id));

        return parent::_afterLoad($object);
    }

    /**
     * @param int $id Question.
     * @return array<string, int[]> Identifiants par type d'entité.
     */
    private function loadAssignments(int $id): array
    {
        $connection = $this->getConnection();
        $assignments = array_fill_keys(self::ENTITY_TYPES, []);
        if ($id <= 0) {
            return $assignments;
        }
        foreach ($connection->fetchAll(
            $connection->select()->from($this->getTable(self::TABLE_ASSIGNMENT), ['entity_type', 'entity_id'])
                ->where('question_id = ?', $id)->order('assignment_id ASC')
        ) as $row) {
            if (isset($assignments[$row['entity_type']])) {
                $assignments[$row['entity_type']][] = (int) $row['entity_id'];
            }
        }

        return $assignments;
    }

    /**
     * Identifiant d'une question importée, d'après son origine.
     *
     * @param string $source Origine (« mageprince », « mageworx »...).
     * @param string $ref    Identifiant dans l'origine.
     * @return int|null
     */
    public function findIdBySource(string $source, string $ref): ?int
    {
        $connection = $this->getConnection();
        $id = $connection->fetchOne(
            $connection->select()->from($this->getMainTable(), ['question_id'])
                ->where('source = ?', $source)->where('source_ref = ?', $ref)
        );

        return $id === false ? null : (int) $id;
    }

    /**
     * @param int $id Question.
     * @return array<int, array{question:string,answer:string}> Textes par vue de magasin.
     */
    private function loadTexts(int $id): array
    {
        if ($id <= 0) {
            return [];
        }
        $connection = $this->getConnection();
        $texts = [];
        foreach ($connection->fetchAll(
            $connection->select()->from($this->getTable(self::TABLE_TEXT), ['store_id', 'question', 'answer'])
                ->where('question_id = ?', $id)
        ) as $row) {
            $texts[(int) $row['store_id']] = ['question' => (string) $row['question'], 'answer' => (string) $row['answer']];
        }

        return $texts;
    }

    /**
     * @param int                                                $id    Question.
     * @param array<int|string, array{question?:string,answer?:string}> $texts Textes par vue de magasin.
     * @return void
     */
    private function saveTexts(int $id, array $texts): void
    {
        $rows = [];
        foreach ($texts as $storeId => $text) {
            $question = trim((string) ($text['question'] ?? ''));
            if ($question === '' && (int) $storeId !== 0) {
                continue;
            }
            $rows[(int) $storeId] = [
                'question_id' => $id,
                'store_id' => (int) $storeId,
                'question' => $question,
                'answer' => (string) ($text['answer'] ?? ''),
            ];
        }
        $connection = $this->getConnection();
        $table = $this->getTable(self::TABLE_TEXT);
        $connection->delete($table, ['question_id = ?' => $id, 'store_id NOT IN (?)' => array_keys($rows) ?: [0]]);
        if ($rows) {
            $connection->insertOnDuplicate($table, array_values($rows), ['question', 'answer']);
        }
    }

    /**
     * @param int                    $id          Question.
     * @param array<string, mixed[]> $assignments Identifiants par type d'entité.
     * @return void
     */
    private function saveAssignments(int $id, array $assignments): void
    {
        $rows = [];
        foreach (self::ENTITY_TYPES as $type) {
            foreach ($this->ids((array) ($assignments[$type] ?? [])) as $entityId) {
                if ($entityId > 0) {
                    $rows[] = ['question_id' => $id, 'entity_type' => $type, 'entity_id' => $entityId];
                }
            }
        }
        $connection = $this->getConnection();
        $table = $this->getTable(self::TABLE_ASSIGNMENT);
        $connection->delete($table, ['question_id = ?' => $id]);
        if ($rows) {
            $connection->insertOnDuplicate($table, $rows, ['entity_id']);
        }
    }

    /**
     * Remplace une liste liée (clé = question, valeur = autre identifiant).
     *
     * @param string $table  Table (sans préfixe).
     * @param string $column Colonne de la valeur.
     * @param int    $id     Question.
     * @param int[]  $values Nouvelles valeurs.
     * @return void
     */
    private function replaceLinks(string $table, string $column, int $id, array $values): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable($table);
        $connection->delete($table, ['question_id = ?' => $id]);
        if ($values) {
            $rows = [];
            foreach ($values as $value) {
                $rows[] = ['question_id' => $id, $column => $value];
            }
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * @param string $table  Table (sans préfixe).
     * @param string $column Colonne de la valeur.
     * @param int    $id     Question.
     * @return int[]
     */
    private function loadLinks(string $table, string $column, int $id): array
    {
        $connection = $this->getConnection();

        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->getTable($table), [$column])->where('question_id = ?', $id)
                ->order($column . ' ASC')
        ));
    }

    /**
     * @param mixed[] $values Valeurs brutes.
     * @return int[] Entiers positifs ou nuls, sans doublon.
     */
    private function ids(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_numeric($value) && (int) $value >= 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        return array_values($ids);
    }
}
