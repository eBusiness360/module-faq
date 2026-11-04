<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Import\Mapper;

/**
 * Traduit les tables de MageWorx FAQ en groupes et questions Maxcode.
 *
 * Modèle de MageWorx : des blocs (type 1 personnalisé, 2 produit, 3 catégorie, 4 page CMS, 5 spécial) contiennent des
 * couples question/réponse (mageworx_faq_block_item) ; les textes sont par vue de magasin (0 = défaut) ; un bloc est
 * lié à des produits, catégories ou pages (mageworx_faq_entity_link). Les blocs automatiques (is_auto = 1), générés par
 * MageWorx, sont ignorés.
 *
 * Correspondance : un couple question/réponse devient une question Maxcode ; un bloc personnalisé devient un groupe ; un
 * bloc produit, catégorie ou page CMS affecte ses questions aux entités liées ; un bloc spécial (panier, caisse...) n'est
 * pas publié sur la page FAQ. Ces questions ne sont affichées sur la page FAQ que si un bloc-groupe les contient aussi.
 * Seuls un bloc actif et un élément actif comptent : ce qui est désactivé chez MageWorx n'élargit rien. Un texte absent
 * d'une vue de magasin retombe sur celui de la vue 0.
 */
class MageworxMapper
{
    public const SOURCE = 'mageworx';
    private const TYPE_PRODUCT = 'product';
    private const TYPE_CATEGORY = 'category';
    private const TYPE_CMS = 'cms_page';
    private const TYPE_SPECIAL_BLOCK = 5;
    private const GROUP_BLOCK_TYPES = [1];

    /**
     * @param array{
     *   blocks:array<int, array<string, mixed>>,
     *   block_values:array<int, array<string, mixed>>,
     *   block_stores:array<int, array<string, mixed>>,
     *   items:array<int, array<string, mixed>>,
     *   questions:array<int, array<string, mixed>>,
     *   question_values:array<int, array<string, mixed>>,
     *   answers:array<int, array<string, mixed>>,
     *   answer_values:array<int, array<string, mixed>>,
     *   links:array<int, array<string, mixed>>
     * } $raw Lignes lues (blocs non automatiques seulement).
     * @param int[]                $storeIds Vues de magasin de ce site (sans la vue 0).
     * @param array<string, int>   $cmsPages Pages CMS : identifiant => numéro.
     * @return array{groups:list<array<string, mixed>>,questions:list<array<string, mixed>>,warnings:string[],notes:string[]}
     */
    public function map(array $raw, array $storeIds, array $cmsPages): array
    {
        $warnings = [];
        $notes = [];
        $questionActive = $this->flags($raw['questions'], 'question_id');
        $answerActive = $this->flags($raw['answers'], 'answer_id');
        $questionTexts = $this->texts($raw['question_values'], 'question_id', 'title');
        $answerTexts = $this->texts($raw['answer_values'], 'answer_id', 'content');
        $blockTitles = $this->texts($raw['block_values'], 'block_id', 'title');
        $blockStores = [];
        foreach ($raw['block_stores'] as $row) {
            $blockStores[(int) $row['block_id']][] = (int) $row['store_id'];
        }
        $links = [];
        foreach ($raw['links'] as $row) {
            $links[(int) $row['block_id']][] = $row;
        }
        $items = [];
        foreach ($raw['items'] as $row) {
            $items[(int) $row['block_id']][] = $row;
        }

        $groups = [];
        $pairs = [];
        $skippedSpecialLinks = 0;
        $specialBlocks = 0;
        foreach ($raw['blocks'] as $block) {
            $blockId = (int) $block['block_id'];
            $specialBlocks += (int) $block['type'] === self::TYPE_SPECIAL_BLOCK ? 1 : 0;
            $blockActive = (int) $block['is_active'] === 1;
            $isGroup = in_array((int) $block['type'], self::GROUP_BLOCK_TYPES, true);
            $groupRef = null;
            if ($isGroup) {
                $texts = $blockTitles[$blockId] ?? [];
                $title = $this->plain((string) ($texts[0] ?? reset($texts) ?: ''));
                if ($title === '') {
                    $title = $this->plain((string) ($block['identifier'] ?? '')) ?: 'FAQ ' . $blockId;
                }
                $groupRef = 'b' . $blockId;
                $translations = [];
                foreach ($texts as $storeId => $text) {
                    if ($storeId > 0 && in_array($storeId, $storeIds, true) && $this->plain((string) $text) !== '') {
                        $translations[$storeId] = $this->plain((string) $text);
                    }
                }
                $groups[] = [
                    'source' => self::SOURCE,
                    'ref' => $groupRef,
                    'title' => $title,
                    'translations' => $translations,
                    'sort_order' => (int) $block['sort_order'],
                    'is_active' => $blockActive ? 1 : 0,
                ];
            }
            $assignments = $this->assignments($links[$blockId] ?? [], $cmsPages, $skippedSpecialLinks);
            $stores = $blockStores[$blockId] ?? [];
            foreach ($items[$blockId] ?? [] as $item) {
                $key = (int) $item['question_id'] . '-' . (int) $item['answer_id'];
                $pair = $pairs[$key] ?? [
                    'question_id' => (int) $item['question_id'],
                    'answer_id' => (int) $item['answer_id'],
                    'sort_order' => (int) $item['sort_order'],
                    'active' => false,
                    'group_refs' => [],
                    'stores' => [],
                    'all_stores' => false,
                    'on_page' => false,
                    'assignments' => ['product' => [], 'category' => [], 'cms_page' => []],
                ];
                $pair['sort_order'] = min($pair['sort_order'], (int) $item['sort_order']);
                // Seul ce qui vient d'un bloc actif ET d'un élément actif compte : un bloc ou un élément désactivé
                // chez MageWorx n'élargit ni les groupes, ni les vues de magasin, ni les affectations.
                if ($blockActive && (int) $item['is_active'] === 1) {
                    $pair['active'] = true;
                    if ($isGroup) {
                        $pair['group_refs'][] = $groupRef;
                        $pair['on_page'] = true;
                    }
                    if (!$stores || in_array(0, $stores, true)) {
                        $pair['all_stores'] = true;
                    }
                    $pair['stores'] = array_merge($pair['stores'], $stores);
                    foreach ($assignments as $type => $ids) {
                        $pair['assignments'][$type] = array_values(array_unique(array_merge($pair['assignments'][$type], $ids)));
                    }
                }
                $pairs[$key] = $pair;
            }
        }
        if ($specialBlocks > 0) {
            $notes[] = sprintf(
                '%d MageWorx special block(s) (cart, checkout...) were not turned into groups: their questions are not shown on the FAQ page.',
                $specialBlocks
            );
        }
        if ($skippedSpecialLinks > 0) {
            $notes[] = sprintf(
                '%d MageWorx link(s) to a special page (cart, checkout...) have no equivalent and were not copied.',
                $skippedSpecialLinks
            );
        }

        $questions = [];
        foreach ($pairs as $key => $pair) {
            $qid = $pair['question_id'];
            $aid = $pair['answer_id'];
            $qTexts = $questionTexts[$qid] ?? [];
            $aTexts = $answerTexts[$aid] ?? [];
            $defaultQuestion = $this->plain((string) ($qTexts[0] ?? ''));
            $defaultAnswer = (string) ($aTexts[0] ?? '');
            if ($defaultQuestion === '') {
                $warnings[] = sprintf('MageWorx question/answer %s has no text in the default view: skipped.', $key);
                continue;
            }
            $texts = [0 => ['question' => $defaultQuestion, 'answer' => $defaultAnswer]];
            foreach (array_unique(array_merge(array_keys($qTexts), array_keys($aTexts))) as $storeId) {
                if ($storeId > 0 && in_array($storeId, $storeIds, true)) {
                    $texts[$storeId] = [
                        'question' => $this->plain((string) ($qTexts[$storeId] ?? '')) ?: $defaultQuestion,
                        'answer' => (string) ($aTexts[$storeId] ?? '') !== '' ? (string) $aTexts[$storeId] : $defaultAnswer,
                    ];
                }
            }
            $stores = $pair['all_stores'] || !$pair['stores']
                ? [0]
                : array_values(array_unique(array_filter($pair['stores'], fn (int $s): bool => in_array($s, $storeIds, true))));
            $draft = false;
            if (!$stores) {
                $stores = [0];
                $draft = true;
                $warnings[] = sprintf('MageWorx question/answer %s imported as a draft: its store views do not exist on this site.', $key);
            }
            $active = $pair['active']
                && ($questionActive[$qid] ?? true)
                && ($answerActive[$aid] ?? true)
                && $defaultAnswer !== ''
                && !$draft;
            $questions[] = [
                'source' => self::SOURCE,
                'ref' => $key,
                'status' => $active ? 1 : 0,
                'sort_order' => $pair['sort_order'],
                'show_on_page' => $pair['on_page'] ? 1 : 0,
                'texts' => $texts,
                'store_ids' => $stores,
                'customer_group_ids' => [],
                'group_refs' => array_values(array_unique($pair['group_refs'])),
                'assignments' => $pair['assignments'],
            ];
        }

        return ['groups' => $groups, 'questions' => $questions, 'warnings' => $warnings, 'notes' => $notes];
    }

    /**
     * @param array<int, array<string, mixed>> $links          Liens du bloc.
     * @param array<string, int>               $cmsPages       Pages CMS : identifiant => numéro.
     * @param int                              $skippedSpecial Compteur des liens « spécial » (complété).
     * @return array{product:int[],category:int[],cms_page:int[]}
     */
    private function assignments(array $links, array $cmsPages, int &$skippedSpecial): array
    {
        $result = ['product' => [], 'category' => [], 'cms_page' => []];
        foreach ($links as $link) {
            $type = (string) $link['entity_type'];
            if ($type === self::TYPE_PRODUCT || $type === self::TYPE_CATEGORY) {
                if ((int) ($link['entity_id'] ?? 0) > 0) {
                    $result[$type][] = (int) $link['entity_id'];
                }
            } elseif ($type === self::TYPE_CMS) {
                $id = (int) ($link['entity_id'] ?? 0);
                if ($id === 0) {
                    $id = $cmsPages[(string) ($link['entity_identifier'] ?? '')] ?? 0;
                }
                if ($id > 0) {
                    $result['cms_page'][] = $id;
                }
            } else {
                $skippedSpecial++;
            }
        }

        return array_map(static fn (array $ids): array => array_values(array_unique($ids)), $result);
    }

    /**
     * @param array<int, array<string, mixed>> $rows  Lignes avec is_active.
     * @param string                           $idKey Colonne d'identifiant.
     * @return array<int, bool>
     */
    private function flags(array $rows, string $idKey): array
    {
        $flags = [];
        foreach ($rows as $row) {
            $flags[(int) $row[$idKey]] = (int) $row['is_active'] === 1;
        }

        return $flags;
    }

    /**
     * @param array<int, array<string, mixed>> $rows     Lignes de valeurs par vue de magasin.
     * @param string                           $idKey    Colonne d'identifiant.
     * @param string                           $textKey  Colonne de texte.
     * @return array<int, array<int, string>> Identifiant => vue de magasin => texte.
     */
    private function texts(array $rows, string $idKey, string $textKey): array
    {
        $texts = [];
        foreach ($rows as $row) {
            $texts[(int) $row[$idKey]][(int) $row['store_id']] = (string) ($row[$textKey] ?? '');
        }

        return $texts;
    }

    /**
     * @param string $text Texte de la source.
     * @return string Texte brut sur une ligne.
     */
    private function plain(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
