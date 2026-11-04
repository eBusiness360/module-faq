<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Admin;

use Maxcode\Faq\Model\Question;

/**
 * Transforme ce qu'envoient les formulaires d'administration en données de modèle.
 */
class PostMapper
{
    /**
     * @param ProductSkus $productSkus Références de produits.
     */
    public function __construct(private readonly ProductSkus $productSkus)
    {
    }

    /**
     * @param array<string, mixed> $post Données envoyées par le formulaire d'une question.
     * @return array{data:array<string, mixed>,unknown_skus:string[]}
     */
    public function question(array $post): array
    {
        $texts = [0 => [
            'question' => $this->plain((string) ($post['question'] ?? '')),
            'answer' => (string) ($post['answer'] ?? ''),
        ]];
        foreach ((array) ($post['translations'] ?? []) as $row) {
            if (!is_array($row) || !empty($row['delete']) || (int) ($row['store_id'] ?? 0) <= 0) {
                continue;
            }
            $texts[(int) $row['store_id']] = [
                'question' => $this->plain((string) ($row['question'] ?? '')),
                'answer' => (string) ($row['answer'] ?? ''),
            ];
        }

        $stores = $this->ids($post['store_ids'] ?? []);
        if (!$stores || in_array(0, $stores, true)) {
            $stores = [0];
        }
        $skus = $this->productSkus->resolve($this->productSkus->parse((string) ($post['product_skus'] ?? '')));

        return [
            'data' => [
                'status' => (int) ($post['status'] ?? 0) === Question::STATUS_ACTIVE
                    ? Question::STATUS_ACTIVE : Question::STATUS_DRAFT,
                'sort_order' => (int) ($post['sort_order'] ?? 0),
                'show_on_page' => empty($post['show_on_page']) ? 0 : 1,
                'texts' => $texts,
                'store_ids' => $stores,
                'group_ids' => $this->ids($post['group_ids'] ?? []),
                'customer_group_ids' => $this->ids($post['customer_group_ids'] ?? []),
                'assignments' => [
                    'product' => $skus['ids'],
                    'category' => $this->ids($post['category_ids'] ?? []),
                    'cms_page' => $this->ids($post['cms_page_ids'] ?? []),
                ],
            ],
            'unknown_skus' => $skus['unknown'],
        ];
    }

    /**
     * @param array<string, mixed> $post Données envoyées par le formulaire d'un groupe.
     * @return array<string, mixed>
     */
    public function group(array $post): array
    {
        $texts = [0 => trim((string) ($post['title'] ?? ''))];
        foreach ((array) ($post['translations'] ?? []) as $row) {
            if (!is_array($row) || !empty($row['delete']) || (int) ($row['store_id'] ?? 0) <= 0) {
                continue;
            }
            $texts[(int) $row['store_id']] = trim((string) ($row['title'] ?? ''));
        }

        return [
            'is_active' => empty($post['is_active']) ? 0 : 1,
            'sort_order' => (int) ($post['sort_order'] ?? 0),
            'texts' => $texts,
        ];
    }

    /**
     * @param string $text Question saisie.
     * @return string Texte sur une ligne. Aucune balise n'est retirée : la question est affichée échappée, jamais
     *                interprétée, et « < 5 min et > 2 min » est un texte légitime.
     */
    private function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @param mixed $values Liste de valeurs envoyées.
     * @return int[] Entiers positifs ou nuls, sans doublon.
     */
    private function ids(mixed $values): array
    {
        $ids = [];
        foreach ((array) $values as $value) {
            if (is_numeric($value) && (int) $value >= 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        return array_values($ids);
    }
}
