<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Maxcode\Faq\Model\Config\Settings;

/**
 * Reconnaît l'adresse de la page FAQ réglée dans la configuration (par vue de magasin) et l'envoie à
 * maxcode_faq/index/index.
 */
class Router implements RouterInterface
{
    /**
     * @param ActionFactory         $actionFactory Fabrique d'actions.
     * @param Settings              $settings      Réglages de la FAQ.
     * @param StoreManagerInterface $storeManager  Vues de magasin.
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Settings $settings,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $key = mb_strtolower($this->settings->getUrlKey($storeId));
        $path = mb_strtolower(trim((string) $request->getPathInfo(), '/'));
        if ($key === '' || $path !== $key || !$this->settings->isEnabled($storeId)) {
            return null;
        }
        $request->setModuleName('maxcode_faq')->setControllerName('index')->setActionName('index');
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, $path);

        return $this->actionFactory->create(Forward::class);
    }
}
