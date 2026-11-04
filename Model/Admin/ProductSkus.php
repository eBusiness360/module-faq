<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Admin;

use Magento\Framework\App\ResourceConnection;

/**
 * Passe des références de produits (SKU, une par ligne ou séparées par des virgules) aux numéros de produits et
 * inversement, pour l'affectation des questions aux fiches produit.
 */
class ProductSkus
{
    public const MAX_SKUS = 500;

    /**
     * @param ResourceConnection $resource Connexion à la base.
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param string $text Texte saisi.
     * @return string[] Références, sans doublon ni ligne vide.
     */
    public function parse(string $text): array
    {
        $skus = [];
        foreach (preg_split('/[\r\n,;\t]+/', $text) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && mb_strlen($part) <= 64) {
                $skus[mb_strtolower($part)] ??= $part;
            }
        }

        return array_slice(array_values($skus), 0, self::MAX_SKUS);
    }

    /**
     * @param string[] $skus Références.
     * @return array{ids:int[],unknown:string[]} Numéros trouvés et références inconnues.
     */
    public function resolve(array $skus): array
    {
        if (!$skus) {
            return ['ids' => [], 'unknown' => []];
        }
        $connection = $this->resource->getConnection();
        $found = $connection->fetchPairs(
            $connection->select()
                ->from($this->resource->getTableName('catalog_product_entity'), ['sku', 'entity_id'])
                ->where('sku IN (?)', $skus)
        );
        $byLower = [];
        foreach ($found as $sku => $id) {
            $byLower[mb_strtolower((string) $sku)] = (int) $id;
        }
        $ids = [];
        $unknown = [];
        foreach ($skus as $sku) {
            if (isset($byLower[mb_strtolower($sku)])) {
                $ids[] = $byLower[mb_strtolower($sku)];
            } else {
                $unknown[] = $sku;
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'unknown' => $unknown];
    }

    /**
     * @param int[] $ids Numéros de produits.
     * @return string Références, une par ligne.
     */
    public function toText(array $ids): string
    {
        if (!$ids) {
            return '';
        }
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($this->resource->getTableName('catalog_product_entity'), ['entity_id', 'sku'])
                ->where('entity_id IN (?)', array_map('intval', $ids))
        );

        return implode("\n", array_map('strval', array_values($rows)));
    }
}
