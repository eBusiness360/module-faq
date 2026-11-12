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
use Maxcode\Faq\Controller\Adminhtml\Question\Save;
use Maxcode\Faq\Model\Admin\ProductSkus;
use Maxcode\Faq\Model\QuestionFactory;
use Maxcode\Faq\Model\ResourceModel\Question as QuestionResource;
use Maxcode\Faq\Model\ResourceModel\Question\CollectionFactory;

/**
 * Données du formulaire d'une question : texte par défaut, traductions, listes liées. Après une erreur
 * d'enregistrement, la saisie gardée par le contrôleur est réaffichée.
 */
class QuestionForm extends AbstractDataProvider
{
    /**
     * @param string                 $name              Nom du fournisseur.
     * @param string                 $primaryFieldName  Champ d'identifiant.
     * @param string                 $requestFieldName  Paramètre d'identifiant dans l'adresse.
     * @param CollectionFactory      $collectionFactory Questions.
     * @param RequestInterface       $request           Requête.
     * @param QuestionFactory        $questionFactory   Fabrique de questions.
     * @param QuestionResource       $questionResource  Ressource des questions.
     * @param ProductSkus            $productSkus       Références de produits.
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
        private readonly QuestionFactory $questionFactory,
        private readonly QuestionResource $questionResource,
        private readonly ProductSkus $productSkus,
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
        if (is_array($persisted) && (int) ($persisted['question_id'] ?? 0) === $id) {
            $this->dataPersistor->clear(Save::PERSISTOR_KEY);

            return [$id ?: '' => $persisted];
        }
        if ($id <= 0) {
            // Une question de départ peut venir de l'adresse (par exemple une recherche sans résultat de Search Insights).
            $start = trim(strip_tags((string) $this->request->getParam('question')));

            return $start === '' ? [] : ['' => ['question' => mb_substr($start, 0, 250), 'status' => '0']];
        }
        $question = $this->questionFactory->create();
        $this->questionResource->load($question, $id);
        if (!$question->getId()) {
            return [];
        }
        $texts = (array) $question->getData('texts');
        $assignments = (array) $question->getData('assignments');
        $translations = [];
        foreach ($texts as $storeId => $text) {
            if ((int) $storeId > 0) {
                $translations[] = [
                    'record_id' => (string) $storeId,
                    'store_id' => (string) $storeId,
                    'question' => $text['question'],
                    'answer' => $text['answer'],
                ];
            }
        }

        return [$id => [
            'question_id' => $id,
            'status' => (string) (int) $question->getData('status'),
            'show_on_page' => (int) $question->getData('show_on_page'),
            'sort_order' => (int) $question->getData('sort_order'),
            'question' => $texts[0]['question'] ?? '',
            'answer' => $texts[0]['answer'] ?? '',
            'translations' => $translations,
            'store_ids' => $this->strings((array) $question->getData('store_ids')),
            'group_ids' => $this->strings((array) $question->getData('group_ids')),
            'customer_group_ids' => $this->strings((array) $question->getData('customer_group_ids')),
            'category_ids' => $this->strings($assignments['category'] ?? []),
            'cms_page_ids' => $this->strings($assignments['cms_page'] ?? []),
            'product_skus' => $this->productSkus->toText($assignments['product'] ?? []),
        ]];
    }

    /**
     * @param array $values Valeurs.
     * @return string[] Les listes d'un formulaire se comparent en chaînes.
     */
    private function strings(array $values): array
    {
        return array_map('strval', array_values($values));
    }
}
