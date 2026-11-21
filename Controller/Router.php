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
use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\Response\Http as HttpResponse;
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
     * @param HttpResponse          $response      Réponse HTTP (redirection de l'adresse avec « / » final).
     */
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Settings $settings,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpResponse $response
    ) {
    }

    /**
     * @inheritdoc
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $urlKey = $this->settings->getUrlKey($storeId);
        $key = mb_strtolower($urlKey);
        $rawPath = (string) $request->getPathInfo();
        $path = mb_strtolower(trim($rawPath, '/'));
        if ($key === '' || $path !== $key || !$this->settings->isEnabled($storeId)) {
            return null;
        }
        // L'adresse avec « / » final (ou une autre casse) n'est pas une seconde page : 301 vers l'adresse réglée, la seule canonique.
        if ($rawPath !== '/' . $urlKey) {
            $this->response->setRedirect($store->getBaseUrl() . $urlKey, 301);
            $request->setDispatched(true);

            return $this->actionFactory->create(Redirect::class);
        }
        $request->setModuleName('maxcode_faq')->setControllerName('index')->setActionName('index');
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, $path);

        return $this->actionFactory->create(Forward::class);
    }
}
