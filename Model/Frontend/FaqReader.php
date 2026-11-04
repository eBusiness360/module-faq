<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Frontend;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

/**
 * Lit les questions visibles pour un visiteur : actives, visibles dans sa vue de magasin et pour son groupe de clients.
 *
 * Le texte d'une vue de magasin remplace le texte par défaut (vue 0) en bloc, question et réponse ensemble.
 */
class FaqReader
{
    /** Plafond de questions par lecture : une FAQ de plus de 500 questions par bloc n'est pas lisible. */
    public const MAX_QUESTIONS = 500;

    /**
     * @param ResourceConnection $resource Connexion à la base.
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param int   $storeId         Vue de magasin du visiteur.
     * @param int   $customerGroupId Groupe de clients du visiteur (0 : non connecté).
     * @param array{on_page?:bool,group_ids?:int[],question_ids?:int[],entity?:array{0:string,1:int},limit?:int} $criteria
     *        on_page : seulement les questions « affichées sur la page FAQ » ; group_ids / question_ids : celles de ces
     *        groupes actifs OU ces numéros ; entity : [type, identifiant] des affectations.
     * @return list<array{id:int,question:string,answer:string,group_ids:int[]}> Dans l'ordre d'affichage.
     */
    public function questions(int $storeId, int $customerGroupId, array $criteria = []): array
    {
        $connection = $this->resource->getConnection();
        $table = fn (string $name): string => $this->resource->getTableName($name);
        // Une traduction est un couple : sans question OU sans réponse, c'est le texte par défaut qui s'affiche.
        $translated = "(ts.question IS NOT NULL AND ts.question <> '' AND ts.answer IS NOT NULL AND ts.answer <> '')";
        $select = $connection->select()
            ->from(['q' => $table('maxcode_faq_question')], ['id' => 'question_id'])
            ->joinLeft(
                ['t0' => $table('maxcode_faq_question_text')],
                't0.question_id = q.question_id AND t0.store_id = 0',
                []
            )
            ->joinLeft(
                ['ts' => $table('maxcode_faq_question_text')],
                $connection->quoteInto('ts.question_id = q.question_id AND ts.store_id = ?', $storeId),
                []
            )
            ->columns([
                'question' => new Expression("IF($translated, ts.question, t0.question)"),
                'answer' => new Expression("IF($translated, ts.answer, t0.answer)"),
            ])
            ->where('q.status = 1')
            ->where("($translated OR (t0.question IS NOT NULL AND t0.question <> ''))")
            // Visible seulement s'il existe une ligne pour cette vue de magasin ou pour « toutes » (0) : une question
            // sans aucune ligne (vue de magasin supprimée, insertion incomplète) n'est montrée nulle part.
            ->where(
                'EXISTS (SELECT 1 FROM ' . $table('maxcode_faq_question_store')
                . $connection->quoteInto(' s WHERE s.question_id = q.question_id AND s.store_id IN (0, ?))', $storeId)
            )
            ->where(
                'NOT EXISTS (SELECT 1 FROM ' . $table('maxcode_faq_question_customer_group')
                . ' c WHERE c.question_id = q.question_id) OR EXISTS (SELECT 1 FROM '
                . $table('maxcode_faq_question_customer_group')
                . $connection->quoteInto(
                    ' c WHERE c.question_id = q.question_id AND c.customer_group_id = ?)',
                    $customerGroupId
                )
            )
            ->order(['q.sort_order ASC', 'q.question_id ASC'])
            ->limit(min(self::MAX_QUESTIONS, max(1, (int) ($criteria['limit'] ?? self::MAX_QUESTIONS))));

        if (!empty($criteria['on_page'])) {
            // Sur la page FAQ : une question rangée dans des groupes qui sont tous désactivés n'apparaît pas (désactiver
            // un groupe cache ses questions) ; une question sans aucun groupe apparaît à la fin de la page.
            $select->where('q.show_on_page = 1')->where(
                'NOT EXISTS (SELECT 1 FROM ' . $table('maxcode_faq_question_group')
                . ' ng WHERE ng.question_id = q.question_id) OR EXISTS (SELECT 1 FROM '
                . $table('maxcode_faq_question_group') . ' ag JOIN ' . $table('maxcode_faq_group')
                . ' ags ON ags.group_id = ag.group_id AND ags.is_active = 1 WHERE ag.question_id = q.question_id)'
            );
        }
        $this->restrict($select, $criteria, $table);

        $rows = $connection->fetchAll($select);
        $links = $this->groupLinks(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        $questions = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $questions[] = [
                'id' => $id,
                'question' => (string) $row['question'],
                'answer' => (string) $row['answer'],
                'group_ids' => $links[$id] ?? [],
            ];
        }

        return $questions;
    }

    /**
     * Groupes actifs avec leur titre dans la vue de magasin.
     *
     * @param int   $storeId Vue de magasin.
     * @param int[] $ids     Seulement ces groupes (vide : tous).
     * @return array<int, array{id:int,title:string}> Dans l'ordre d'affichage, indexé par identifiant.
     */
    public function groups(int $storeId, array $ids = []): array
    {
        $connection = $this->resource->getConnection();
        $translated = "(gs.title IS NOT NULL AND gs.title <> '')";
        $select = $connection->select()
            ->from(['g' => $this->resource->getTableName('maxcode_faq_group')], ['id' => 'group_id'])
            ->joinLeft(
                ['g0' => $this->resource->getTableName('maxcode_faq_group_text')],
                'g0.group_id = g.group_id AND g0.store_id = 0',
                []
            )
            ->joinLeft(
                ['gs' => $this->resource->getTableName('maxcode_faq_group_text')],
                $connection->quoteInto('gs.group_id = g.group_id AND gs.store_id = ?', $storeId),
                []
            )
            ->columns(['title' => new Expression("IF($translated, gs.title, g0.title)")])
            ->where('g.is_active = 1')
            ->order(['g.sort_order ASC', 'g.group_id ASC']);
        if ($ids) {
            $select->where('g.group_id IN (?)', array_map('intval', $ids));
        }
        $groups = [];
        foreach ($connection->fetchAll($select) as $row) {
            $groups[(int) $row['id']] = ['id' => (int) $row['id'], 'title' => trim((string) $row['title'])];
        }

        return $groups;
    }

    /**
     * @param \Magento\Framework\DB\Select $select   Requête des questions.
     * @param array                        $criteria Critères.
     * @param callable                     $table    Nom de table avec préfixe.
     * @return void
     */
    private function restrict($select, array $criteria, callable $table): void
    {
        $connection = $this->resource->getConnection();
        $or = [];
        $groupIds = array_values(array_filter(array_map('intval', $criteria['group_ids'] ?? [])));
        $questionIds = array_values(array_filter(array_map('intval', $criteria['question_ids'] ?? [])));
        if ($groupIds) {
            $or[] = 'EXISTS (SELECT 1 FROM ' . $table('maxcode_faq_question_group') . ' qg JOIN '
                . $table('maxcode_faq_group') . ' g ON g.group_id = qg.group_id AND g.is_active = 1 '
                . $connection->quoteInto('WHERE qg.question_id = q.question_id AND qg.group_id IN (?))', $groupIds);
        }
        if ($questionIds) {
            $or[] = $connection->quoteInto('q.question_id IN (?)', $questionIds);
        }
        if ($or) {
            $select->where('(' . implode(' OR ', $or) . ')');
        } elseif (isset($criteria['group_ids']) || isset($criteria['question_ids'])) {
            // Des critères de groupe ou de numéro ont été demandés mais rien de valable : aucune question.
            $select->where('1 = 0');
        }
        if (isset($criteria['entity'])) {
            [$type, $entityId] = $criteria['entity'];
            $select->where(
                'EXISTS (SELECT 1 FROM ' . $table('maxcode_faq_assignment')
                . $connection->quoteInto(' a WHERE a.question_id = q.question_id AND a.entity_type = ?', (string) $type)
                . $connection->quoteInto(' AND a.entity_id = ?)', (int) $entityId)
            );
        }
    }

    /**
     * @param int[] $questionIds Questions.
     * @return array<int, int[]> Groupes actifs de chaque question.
     */
    private function groupLinks(array $questionIds): array
    {
        if (!$questionIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['qg' => $this->resource->getTableName('maxcode_faq_question_group')], ['question_id', 'group_id'])
                ->join(
                    ['g' => $this->resource->getTableName('maxcode_faq_group')],
                    'g.group_id = qg.group_id AND g.is_active = 1',
                    []
                )
                ->where('qg.question_id IN (?)', $questionIds)
        );
        $links = [];
        foreach ($rows as $row) {
            $links[(int) $row['question_id']][] = (int) $row['group_id'];
        }

        return $links;
    }
}
