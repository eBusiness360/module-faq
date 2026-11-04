<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Maxcode\Faq\Model\Config\Settings;

/**
 * La page FAQ.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory           $pageFactory    Fabrique de pages.
     * @param ForwardFactory        $forwardFactory Redirection interne (page introuvable).
     * @param Settings              $settings       Réglages de la FAQ.
     * @param StoreManagerInterface $storeManager   Vues de magasin.
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Settings $settings,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return Page|Forward
     */
    public function execute()
    {
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $key = $this->settings->getUrlKey($storeId);
        if ($key === '' || !$this->settings->isEnabled($storeId)) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $config = $page->getConfig();
        $title = $this->settings->getPageTitle($storeId);
        $config->getTitle()->set($this->settings->getMetaTitle($storeId) ?: $title);
        $description = $this->settings->getMetaDescription($storeId);
        if ($description !== '') {
            $config->setDescription($description);
        }
        $config->addRemotePageAsset(
            $store->getBaseUrl() . $key,
            'canonical',
            ['attributes' => ['rel' => 'canonical']]
        );
        // Le titre visible (h1) peut différer de la balise title.
        $heading = $page->getLayout()->getBlock('page.main.title');
        if ($heading && $title !== '') {
            $heading->setPageTitle($title);
        }

        return $page;
    }
}
