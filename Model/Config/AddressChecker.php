<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Config;

use Magento\Framework\App\ResourceConnection;

/**
 * Cherche ce qui sert déjà une adresse de la boutique : une page CMS (identifiant) ou une réécriture d'adresse
 * (produit, catégorie, page, redirection). Le routeur de la FAQ passe après celui des réécritures d'adresse : une
 * page FAQ réglée sur une adresse déjà prise ne s'afficherait jamais, sans aucun message.
 */
class AddressChecker
{
    /**
     * @param ResourceConnection $resource Connexion à la base.
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param string $address Adresse, sans barre au début ni à la fin.
     * @return string|null Ce qui la sert déjà (« page CMS », « réécriture d'adresse »), null si elle est libre.
     */
    public function findOwner(string $address): ?string
    {
        if ($address === '') {
            return null;
        }
        $connection = $this->resource->getConnection();
        $cms = $connection->fetchOne(
            $connection->select()->from($this->resource->getTableName('cms_page'), ['page_id'])
                ->where('identifier = ?', $address)->limit(1)
        );
        if ($cms !== false && $cms !== null) {
            return 'CMS page';
        }
        $rewrite = $connection->fetchOne(
            $connection->select()->from($this->resource->getTableName('url_rewrite'), ['url_rewrite_id'])
                ->where('request_path IN (?)', [$address, $address . '/'])->limit(1)
        );

        return $rewrite !== false && $rewrite !== null ? 'URL rewrite' : null;
    }
}
