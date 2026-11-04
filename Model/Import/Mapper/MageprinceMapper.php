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
 * Traduit les lignes de Mageprince FAQ (tables prince_faq et prince_faqgroup) en groupes et questions Maxcode.
 *
 * Règles de Mageprince reprises :
 * - « storeview », « customer_group » et « group » sont des listes d'identifiants séparés par des virgules ;
 * - vue de magasin vide ou 0 : toutes les vues ; groupe de clients vide : tous ;
 * - une question n'est affichée que dans un groupe ACTIF, et seulement si le groupe ET la question sont visibles
 *   pour la vue de magasin et le groupe de clients du visiteur : les restrictions du groupe s'ajoutent donc à celles de
 *   la question (intersection) ;
 * - une question sans groupe s'affichait dans tous ses groupes : elle devient une question sans groupe, affichée à la
 *   fin de la page FAQ.
 *
 * Principe de prudence : une question n'est jamais affichée plus largement que dans la source. Si elle n'est visible
 * nulle part (vues de magasin ou groupes de clients inexistants sur ce site, groupes tous désactivés, restrictions qui
 * ne se recoupent pas), ou si ses groupes ont des restrictions différentes (Maxcode ne sait pas exprimer une union de
 * couples « vue de magasin + groupe de clients »), elle est importée en brouillon et signalée.
 */
class MageprinceMapper
{
    public const SOURCE = 'mageprince';

    /**
     * @param array{groups:array<int, array<string, mixed>>,questions:array<int, array<string, mixed>>} $raw Lignes lues.
     * @param int[] $storeIds         Vues de magasin de ce site (sans la vue 0).
     * @param int[] $customerGroupIds Groupes de clients de ce site.
     * @return array{groups:list<array<string, mixed>>,questions:list<array<string, mixed>>,warnings:string[],notes:string[]}
     */
    public function map(array $raw, array $storeIds, array $customerGroupIds): array
    {
        $storeIds = $this->sorted($storeIds);
        $customerGroupIds = $this->sorted($customerGroupIds);
        $groups = [];
        $warnings = [];
        $known = [];
        foreach ($raw['groups'] as $row) {
            $ref = (string) (int) $row['faqgroup_id'];
            $title = $this->plain((string) ($row['groupname'] ?? ''));
            if ($title === '') {
                $warnings[] = sprintf('Mageprince group #%s has no name: skipped.', $ref);
                continue;
            }
            $active = (int) ($row['status'] ?? 1) === 1;
            $known[$ref] = [
                'active' => $active,
                'stores' => $this->storeSet($row['storeview'] ?? null, $storeIds),
                'customers' => $this->customerSet($row['customer_group'] ?? null, $customerGroupIds),
            ];
            $groups[] = [
                'source' => self::SOURCE,
                'ref' => $ref,
                'title' => $title,
                'sort_order' => (int) ($row['sortorder'] ?? 0),
                'is_active' => $active ? 1 : 0,
            ];
        }

        $questions = [];
        foreach ($raw['questions'] as $row) {
            $ref = (string) (int) $row['faq_id'];
            $question = $this->plain((string) ($row['title'] ?? ''));
            if ($question === '') {
                $warnings[] = sprintf('Mageprince question #%s has no text: skipped.', $ref);
                continue;
            }
            $groupRefs = [];
            foreach ($this->csv($row['group'] ?? null) as $groupId) {
                if (isset($known[(string) $groupId])) {
                    $groupRefs[] = (string) $groupId;
                } else {
                    $warnings[] = sprintf('Mageprince question #%s: group #%d is unknown, ignored.', $ref, $groupId);
                }
            }
            $groupRefs = array_values(array_unique($groupRefs));

            $reasons = [];
            [$stores, $customers] = $this->visibility($row, $groupRefs, $known, $storeIds, $customerGroupIds, $reasons);
            foreach ($reasons as $reason) {
                $warnings[] = sprintf('Mageprince question #%s imported as a draft: %s.', $ref, $reason);
            }
            $questions[] = [
                'source' => self::SOURCE,
                'ref' => $ref,
                'status' => (int) ($row['status'] ?? 1) === 1 && !$reasons ? 1 : 0,
                'sort_order' => (int) ($row['sortorder'] ?? 0),
                'show_on_page' => 1,
                'texts' => [0 => ['question' => $question, 'answer' => (string) ($row['content'] ?? '')]],
                'store_ids' => $reasons ? [0] : $this->asStoreList($stores, $storeIds),
                'customer_group_ids' => $reasons ? [] : $this->asCustomerList($customers, $customerGroupIds),
                'group_refs' => $groupRefs,
                'assignments' => ['product' => [], 'category' => [], 'cms_page' => []],
            ];
        }

        return ['groups' => $groups, 'questions' => $questions, 'warnings' => $warnings, 'notes' => []];
    }

    /**
     * Vues de magasin et groupes de clients où la question est réellement visible chez Mageprince.
     *
     * @param array<string, mixed>                                                                $row              Ligne de prince_faq.
     * @param string[]                                                                            $groupRefs        Groupes connus de la question.
     * @param array<string, array{active:bool,stores:int[],customers:int[]}>                      $known            Groupes de la source.
     * @param int[]                                                                               $storeIds         Vues de magasin du site.
     * @param int[]                                                                               $customerGroupIds Groupes de clients du site.
     * @param string[]                                                                            $reasons          Raisons de passer en brouillon (complétées).
     * @return array{0:int[],1:int[]}
     */
    private function visibility(
        array $row,
        array $groupRefs,
        array $known,
        array $storeIds,
        array $customerGroupIds,
        array &$reasons
    ): array {
        $stores = $this->storeSet($row['storeview'] ?? null, $storeIds);
        $customers = $this->customerSet($row['customer_group'] ?? null, $customerGroupIds);
        if (!$stores && $this->csv($row['storeview'] ?? null)) {
            $reasons[] = 'its store views (' . implode(',', $this->csv($row['storeview'])) . ') do not exist on this site';

            return [[], []];
        }
        if (!$customers && $this->csv($row['customer_group'] ?? null)) {
            $reasons[] = 'its customer groups (' . implode(',', $this->csv($row['customer_group'])) . ') do not exist on this site';

            return [[], []];
        }
        if ($groupRefs) {
            $variants = [];
            foreach ($groupRefs as $ref) {
                if ($known[$ref]['active']) {
                    $variants[json_encode([
                        $this->sorted(array_intersect($stores, $known[$ref]['stores'])),
                        $this->sorted(array_intersect($customers, $known[$ref]['customers'])),
                    ])] = true;
                }
            }
            if (!$variants) {
                $reasons[] = 'all its groups are disabled';

                return [[], []];
            }
            if (count($variants) > 1) {
                $reasons[] = 'its groups have different restrictions (store views or customer groups) that Maxcode cannot combine: check who should see it';

                return [[], []];
            }
            [$stores, $customers] = json_decode((string) array_key_first($variants), true);
        }
        if (!$stores || !$customers) {
            $reasons[] = 'no store view or customer group can see it once its group restrictions are applied';

            return [[], []];
        }

        return [$stores, $customers];
    }

    /**
     * @param mixed $value    Liste d'identifiants séparés par des virgules.
     * @param int[] $universe Vues de magasin du site.
     * @return int[] Les vues de magasin visées (vide ou 0 : toutes).
     */
    private function storeSet(mixed $value, array $universe): array
    {
        $wanted = $this->csv($value);
        if (!$wanted || in_array(0, $wanted, true)) {
            return $universe;
        }

        return $this->sorted(array_intersect($wanted, $universe));
    }

    /**
     * @param mixed $value    Liste d'identifiants séparés par des virgules.
     * @param int[] $universe Groupes de clients du site.
     * @return int[] Les groupes de clients visés (vide : tous).
     */
    private function customerSet(mixed $value, array $universe): array
    {
        $wanted = $this->csv($value);
        if (!$wanted) {
            return $universe;
        }

        return $this->sorted(array_intersect($wanted, $universe));
    }

    /**
     * @param int[] $stores   Vues de magasin visibles.
     * @param int[] $universe Vues de magasin du site.
     * @return int[] [0] quand toutes les vues voient la question.
     */
    private function asStoreList(array $stores, array $universe): array
    {
        return $stores === $universe ? [0] : $stores;
    }

    /**
     * @param int[] $customers Groupes de clients autorisés.
     * @param int[] $universe  Groupes de clients du site.
     * @return int[] Vide quand tous les groupes voient la question.
     */
    private function asCustomerList(array $customers, array $universe): array
    {
        return $customers === $universe ? [] : $customers;
    }

    /**
     * @param mixed $value Liste séparée par des virgules, ou null.
     * @return int[] Entiers positifs ou nuls, sans doublon.
     */
    private function csv(mixed $value): array
    {
        $ids = [];
        foreach (explode(',', (string) $value) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $ids[(int) $part] = (int) $part;
            }
        }

        return array_values($ids);
    }

    /**
     * @param int[] $values Entiers.
     * @return int[] Triés, sans doublon, réindexés.
     */
    private function sorted(array $values): array
    {
        $values = array_values(array_unique(array_map('intval', $values)));
        sort($values);

        return $values;
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
