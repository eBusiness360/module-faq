<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Import;

use Maxcode\Faq\Model\GroupFactory;
use Maxcode\Faq\Model\QuestionFactory;
use Maxcode\Faq\Model\ResourceModel\Group as GroupResource;
use Maxcode\Faq\Model\ResourceModel\Question as QuestionResource;

/**
 * Écrit dans les tables de Maxcode_Faq ce que les convertisseurs ont préparé. Idempotent : un groupe ou une question
 * déjà importé (même origine, même identifiant) est reconnu et laissé tel quel, jamais écrasé ni dupliqué, de sorte
 * que ce qui a été corrigé ensuite dans Maxcode reste.
 */
class Writer
{
    /**
     * @param GroupFactory     $groupFactory     Fabrique de groupes.
     * @param GroupResource    $groupResource    Ressource des groupes.
     * @param QuestionFactory  $questionFactory  Fabrique de questions.
     * @param QuestionResource $questionResource Ressource des questions.
     */
    public function __construct(
        private readonly GroupFactory $groupFactory,
        private readonly GroupResource $groupResource,
        private readonly QuestionFactory $questionFactory,
        private readonly QuestionResource $questionResource
    ) {
    }

    /**
     * @param array{groups:list<array<string, mixed>>,questions:list<array<string, mixed>>} $mapped Données préparées.
     * @param bool $dryRun Vrai : ne rien écrire, compter seulement.
     * @return array{groups_created:int,groups_existing:int,questions_created:int,questions_existing:int,warnings:string[]}
     */
    public function write(array $mapped, bool $dryRun): array
    {
        $report = [
            'groups_created' => 0, 'groups_existing' => 0,
            'questions_created' => 0, 'questions_existing' => 0,
            'warnings' => [],
        ];
        $connection = $this->groupResource->getConnection();
        if (!$dryRun) {
            $connection->beginTransaction();
        }
        try {
            $groupIds = [];
            foreach ($mapped['groups'] as $group) {
                $key = $group['source'] . ':' . $group['ref'];
                $existing = $this->groupResource->findIdBySource($group['source'], $group['ref']);
                if ($existing !== null) {
                    $groupIds[$key] = $existing;
                    $report['groups_existing']++;
                    continue;
                }
                $report['groups_created']++;
                if ($dryRun) {
                    continue;
                }
                $model = $this->groupFactory->create();
                $texts = [0 => $group['title']];
                foreach ((array) ($group['translations'] ?? []) as $storeId => $title) {
                    $texts[(int) $storeId] = $title;
                }
                $model->setData([
                    'source' => $group['source'],
                    'source_ref' => $group['ref'],
                    'sort_order' => $group['sort_order'],
                    'is_active' => $group['is_active'],
                    'texts' => $texts,
                ]);
                $this->groupResource->save($model);
                $groupIds[$key] = (int) $model->getId();
            }

            foreach ($mapped['questions'] as $question) {
                $existing = $this->questionResource->findIdBySource($question['source'], $question['ref']);
                if ($existing !== null) {
                    $report['questions_existing']++;
                    continue;
                }
                $report['questions_created']++;
                if ($dryRun) {
                    continue;
                }
                $ids = [];
                foreach ($question['group_refs'] as $ref) {
                    $id = $groupIds[$question['source'] . ':' . $ref]
                        ?? $this->groupResource->findIdBySource($question['source'], (string) $ref);
                    if ($id === null) {
                        $report['warnings'][] = sprintf('Question %s: group %s was not found, ignored.', $question['ref'], $ref);
                    } else {
                        $ids[] = $id;
                    }
                }
                $model = $this->questionFactory->create();
                $model->setData([
                    'source' => $question['source'],
                    'source_ref' => $question['ref'],
                    'status' => $question['status'],
                    'sort_order' => $question['sort_order'],
                    'show_on_page' => $question['show_on_page'],
                    'texts' => $question['texts'],
                    'store_ids' => $question['store_ids'],
                    'group_ids' => array_values(array_unique($ids)),
                    'customer_group_ids' => $question['customer_group_ids'],
                    'assignments' => $question['assignments'],
                ]);
                $this->questionResource->save($model);
            }
            if (!$dryRun) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if (!$dryRun) {
                $connection->rollBack();
            }
            throw $exception;
        }

        return $report;
    }
}
