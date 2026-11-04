<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Import\Reader;

use Magento\Framework\App\ResourceConnection;

/**
 * Lit, en lecture seule, les tables de MageWorx FAQ (aucune dépendance de code envers ce module). Les blocs
 * automatiques (is_auto = 1) et tout ce qui en dépend sont laissés de côté.
 */
class MageworxReader
{
    private const REQUIRED_TABLES = [
        'mageworx_faq_block', 'mageworx_faq_block_item', 'mageworx_faq_block_value', 'mageworx_faq_block_store',
        'mageworx_faq_question', 'mageworx_faq_question_value', 'mageworx_faq_answer', 'mageworx_faq_answer_value',
        'mageworx_faq_entity_link',
    ];

    /**
     * @param ResourceConnection $resource Connexion à la base.
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @return bool Les tables de MageWorx existent-elles ?
     */
    public function isAvailable(): bool
    {
        $connection = $this->resource->getConnection();
        foreach (self::REQUIRED_TABLES as $table) {
            if (!$connection->isTableExists($this->resource->getTableName($table))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *   blocks:array<int, array<string, mixed>>,
     *   block_values:array<int, array<string, mixed>>,
     *   block_stores:array<int, array<string, mixed>>,
     *   items:array<int, array<string, mixed>>,
     *   questions:array<int, array<string, mixed>>,
     *   question_values:array<int, array<string, mixed>>,
     *   answers:array<int, array<string, mixed>>,
     *   answer_values:array<int, array<string, mixed>>,
     *   links:array<int, array<string, mixed>>
     * }
     */
    public function read(): array
    {
        $connection = $this->resource->getConnection();
        $table = fn (string $name): string => $this->resource->getTableName($name);
        $blocks = $connection->fetchAll(
            $connection->select()->from($table('mageworx_faq_block'))
                ->where('is_auto = 0')->order(['sort_order ASC', 'block_id ASC'])
        );
        $blockIds = array_map(static fn (array $b): int => (int) $b['block_id'], $blocks);
        $byBlock = function (string $name, array $columns = ['*']) use ($connection, $table, $blockIds): array {
            return $blockIds ? $connection->fetchAll(
                $connection->select()->from($table($name), $columns)->where('block_id IN (?)', $blockIds)
            ) : [];
        };
        $items = $byBlock('mageworx_faq_block_item');
        $questionIds = array_values(array_unique(array_map(static fn (array $i): int => (int) $i['question_id'], $items)));
        $answerIds = array_values(array_unique(array_map(static fn (array $i): int => (int) $i['answer_id'], $items)));
        $byId = function (string $name, string $column, array $ids) use ($connection, $table): array {
            return $ids ? $connection->fetchAll(
                $connection->select()->from($table($name))->where($column . ' IN (?)', $ids)
            ) : [];
        };

        return [
            'blocks' => $blocks,
            'block_values' => $byBlock('mageworx_faq_block_value'),
            'block_stores' => $byBlock('mageworx_faq_block_store'),
            'items' => $items,
            'questions' => $byId('mageworx_faq_question', 'question_id', $questionIds),
            'question_values' => $byId('mageworx_faq_question_value', 'question_id', $questionIds),
            'answers' => $byId('mageworx_faq_answer', 'answer_id', $answerIds),
            'answer_values' => $byId('mageworx_faq_answer_value', 'answer_id', $answerIds),
            'links' => $byBlock('mageworx_faq_entity_link'),
        ];
    }
}
