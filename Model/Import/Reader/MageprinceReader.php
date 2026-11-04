<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Import\Reader;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Lit, en lecture seule, les tables de Mageprince FAQ (aucune dépendance de code envers ce module).
 */
class MageprinceReader
{
    private const TABLE_FAQ = 'prince_faq';
    private const TABLE_GROUP = 'prince_faqgroup';
    private const CONFIG_URL = 'faqtab/seo/faq_url';

    /**
     * @param ResourceConnection   $resource    Connexion à la base.
     * @param ScopeConfigInterface $scopeConfig Configuration.
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return bool Les tables de Mageprince existent-elles ?
     */
    public function isAvailable(): bool
    {
        $connection = $this->resource->getConnection();

        return $connection->isTableExists($this->resource->getTableName(self::TABLE_FAQ))
            && $connection->isTableExists($this->resource->getTableName(self::TABLE_GROUP));
    }

    /**
     * @return array{groups:array<int, array<string, mixed>>,questions:array<int, array<string, mixed>>}
     */
    public function read(): array
    {
        $connection = $this->resource->getConnection();

        return [
            'groups' => $connection->fetchAll(
                $connection->select()->from($this->resource->getTableName(self::TABLE_GROUP))
                    ->order(['sortorder ASC', 'faqgroup_id ASC'])
            ),
            'questions' => $connection->fetchAll(
                $connection->select()->from($this->resource->getTableName(self::TABLE_FAQ))
                    ->order(['sortorder ASC', 'faq_id ASC'])
            ),
        ];
    }

    /**
     * @return string Adresse de la page FAQ de Mageprince (réglage par défaut), vide si absente.
     */
    public function pageUrlKey(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::CONFIG_URL), '/');
    }
}
