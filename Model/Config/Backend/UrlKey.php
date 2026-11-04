<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Maxcode\Faq\Model\Config\AddressChecker;

/**
 * Contrôle l'adresse de la page FAQ : minuscules, chiffres, tiret, tiret bas et barres, sans début par un mot réservé
 * par Magento (administration, panier, API...).
 */
class UrlKey extends Value
{
    /** Premiers segments qu'une page FAQ ne doit pas prendre. */
    public const RESERVED = [
        'admin', 'adminhtml', 'checkout', 'customer', 'catalog', 'catalogsearch', 'media', 'static', 'pub',
        'rest', 'soap', 'graphql', 'setup', 'sales', 'wishlist', 'maxcode_faq', 'contact', 'newsletter', 'review',
        'sendfriend', 'multishipping', 'paypal', 'persistent', 'productalert', 'downloadable', 'sitemap', 'search',
        'cms', 'directory',
    ];

    /**
     * @param string $value Adresse saisie.
     * @return string L'adresse en minuscules, sans barre au début ni à la fin (vide : pas de page).
     * @throws LocalizedException Adresse invalide.
     */
    public static function normalize(string $value): string
    {
        $value = trim(mb_strtolower(trim($value)), '/');
        if ($value === '') {
            return '';
        }
        if (preg_match('~^[a-z0-9][a-z0-9_-]*(/[a-z0-9][a-z0-9_-]*)*$~', $value) !== 1 || strlen($value) > 100) {
            throw new LocalizedException(__(
                'The FAQ page address may only contain lower-case letters, digits, hyphens, underscores and single slashes.'
            ));
        }
        $first = explode('/', $value)[0];
        if (in_array($first, self::RESERVED, true)) {
            throw new LocalizedException(__('The FAQ page address cannot start with "%1".', $first));
        }

        return $value;
    }

    /**
     * @param Context               $context               Contexte du modèle.
     * @param Registry              $registry              Registre.
     * @param ScopeConfigInterface  $config                Configuration.
     * @param TypeListInterface     $cacheTypeList         Types de cache.
     * @param AbstractResource|null $resource              Ressource.
     * @param AbstractDb|null       $resourceCollection    Collection.
     * @param array                 $data                  Données.
     * @param AddressChecker|null   $addressChecker        Cherche une adresse déjà servie.
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        private readonly ?AddressChecker $addressChecker = null
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {
        $value = self::normalize((string) $this->getValue());
        // Seulement quand l'adresse change : un conflit ancien ne doit pas empêcher d'enregistrer d'autres réglages.
        if ($value !== '' && $this->isValueChanged() && $this->addressChecker !== null) {
            $owner = $this->addressChecker->findOwner($value);
            if ($owner !== null) {
                throw new LocalizedException(__(
                    'The address "%1" is already used by a %2: choose another address, or rename that page first.',
                    $value,
                    __($owner)
                ));
            }
        }
        $this->setValue($value);

        return parent::beforeSave();
    }
}
