<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Maxcode\Faq\Controller\Adminhtml\Group\Save;
use Maxcode\Faq\Model\GroupFactory;
use Maxcode\Faq\Model\ResourceModel\Group as GroupResource;
use Maxcode\Faq\Model\ResourceModel\Group\CollectionFactory;

/**
 * Données du formulaire d'un groupe : titre par défaut et titres par vue de magasin.
 */
class GroupForm extends AbstractDataProvider
{
    /**
     * @param string                 $name              Nom du fournisseur.
     * @param string                 $primaryFieldName  Champ d'identifiant.
     * @param string                 $requestFieldName  Paramètre d'identifiant dans l'adresse.
     * @param CollectionFactory      $collectionFactory Groupes.
     * @param RequestInterface       $request           Requête.
     * @param GroupFactory           $groupFactory      Fabrique de groupes.
     * @param GroupResource          $groupResource     Ressource des groupes.
     * @param DataPersistorInterface $dataPersistor     Saisie gardée après une erreur.
     * @param array                  $meta              Métadonnées.
     * @param array                  $data              Données.
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly GroupFactory $groupFactory,
        private readonly GroupResource $groupResource,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @inheritdoc
     */
    public function getData(): array
    {
        $id = (int) $this->request->getParam($this->requestFieldName);
        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (is_array($persisted) && (int) ($persisted['group_id'] ?? 0) === $id) {
            $this->dataPersistor->clear(Save::PERSISTOR_KEY);

            return [$id ?: '' => $persisted];
        }
        if ($id <= 0) {
            return [];
        }
        $group = $this->groupFactory->create();
        $this->groupResource->load($group, $id);
        if (!$group->getId()) {
            return [];
        }
        $texts = (array) $group->getData('texts');
        $translations = [];
        foreach ($texts as $storeId => $title) {
            if ((int) $storeId > 0) {
                $translations[] = [
                    'record_id' => (string) $storeId,
                    'store_id' => (string) $storeId,
                    'title' => $title,
                ];
            }
        }

        return [$id => [
            'group_id' => $id,
            'is_active' => (int) $group->getData('is_active'),
            'sort_order' => (int) $group->getData('sort_order'),
            'title' => $texts[0] ?? '',
            'translations' => $translations,
        ]];
    }
}
