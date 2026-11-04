<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Réglages de la FAQ (Stores > Configuration > Maxcode > FAQ).
 *
 * Tous sont lus avec la portée « store » : l'adresse de la page, les titres et les descriptions changent d'une langue à
 * l'autre. Les textes passent par la traduction, pour que les valeurs par défaut (en anglais) suivent la langue de la
 * boutique ; un texte saisi par l'administrateur n'a pas de traduction et reste tel quel.
 */
class Settings
{
    public const PATH_ENABLED = 'maxcode_faq/general/enabled';
    public const PATH_URL_KEY = 'maxcode_faq/page/url_key';
    public const PATH_PAGE_TITLE = 'maxcode_faq/page/title';
    public const PATH_META_TITLE = 'maxcode_faq/page/meta_title';
    public const PATH_META_DESCRIPTION = 'maxcode_faq/page/meta_description';
    public const PATH_BLOCK_TITLE = 'maxcode_faq/display/block_title';
    public const PATH_GROUP_TITLES = 'maxcode_faq/display/group_titles';
    public const PATH_GROUP_TABS = 'maxcode_faq/display/group_tabs';
    public const PATH_EXPAND_ALL = 'maxcode_faq/display/expand_all';
    public const PATH_ON_PRODUCT = 'maxcode_faq/display/on_product';
    public const PATH_ON_CATEGORY = 'maxcode_faq/display/on_category';
    public const PATH_ON_CMS = 'maxcode_faq/display/on_cms';
    public const PATH_JSON_LD = 'maxcode_faq/seo/json_ld';

    /**
     * @param ScopeConfigInterface $scopeConfig Configuration.
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * @param int|null $storeId Vue de magasin (null : la vue courante).
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::PATH_ENABLED, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return string Adresse de la page, sans barre au début ni à la fin ; vide : pas de page.
     */
    public function getUrlKey(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(self::PATH_URL_KEY, ScopeInterface::SCOPE_STORE, $storeId), '/');
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return string
     */
    public function getPageTitle(?int $storeId = null): string
    {
        return $this->text(self::PATH_PAGE_TITLE, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return string Le titre de la page, ou à défaut le titre de la page FAQ.
     */
    public function getMetaTitle(?int $storeId = null): string
    {
        return $this->text(self::PATH_META_TITLE, $storeId) ?: $this->getPageTitle($storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return string
     */
    public function getMetaDescription(?int $storeId = null): string
    {
        return $this->text(self::PATH_META_DESCRIPTION, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return string
     */
    public function getBlockTitle(?int $storeId = null): string
    {
        return $this->text(self::PATH_BLOCK_TITLE, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return bool
     */
    public function showGroupTitles(?int $storeId = null): bool
    {
        return $this->flag(self::PATH_GROUP_TITLES, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return bool
     */
    public function showGroupTabs(?int $storeId = null): bool
    {
        return $this->flag(self::PATH_GROUP_TABS, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return bool
     */
    public function showExpandAll(?int $storeId = null): bool
    {
        return $this->flag(self::PATH_EXPAND_ALL, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return bool
     */
    public function isOnProduct(?int $storeId = null): bool
    {
        return $this->flag(self::PATH_ON_PRODUCT, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return bool
     */
    public function isOnCategory(?int $storeId = null): bool
    {
        return $this->flag(self::PATH_ON_CATEGORY, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return bool
     */
    public function isOnCms(?int $storeId = null): bool
    {
        return $this->flag(self::PATH_ON_CMS, $storeId);
    }

    /**
     * @param int|null $storeId Vue de magasin.
     * @return bool
     */
    public function isJsonLdEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::PATH_JSON_LD, $storeId);
    }

    /**
     * @param string   $path    Chemin de configuration.
     * @param int|null $storeId Vue de magasin.
     * @return bool
     */
    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param string   $path    Chemin de configuration.
     * @param int|null $storeId Vue de magasin.
     * @return string
     */
    private function text(string $path, ?int $storeId): string
    {
        $value = trim((string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));

        return $value === '' ? '' : (string) __($value);
    }
}
