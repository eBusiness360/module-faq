<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Maxcode\Faq\Model\ResourceModel\Question as QuestionResource;

/**
 * Une question de la FAQ, avec ses textes, ses groupes, ses vues de magasin, ses groupes de clients et ses
 * affectations. Les listes ne sont réécrites que si leur clé est présente dans les données : un enregistrement
 * partiel (changement de statut en masse) n'efface donc rien.
 *
 * Clés de données : « texts » (vue de magasin => [question, answer]), « store_ids », « group_ids »,
 * « customer_group_ids », « assignments » (product / category / cms_page => identifiants).
 */
class Question extends AbstractModel implements IdentityInterface
{
    /** Étiquette de cache : vide les pages qui affichent la FAQ (cache de pages) quand une question change. */
    public const CACHE_TAG = 'maxcode_faq';
    public const STATUS_DRAFT = 0;
    public const STATUS_ACTIVE = 1;

    /**
     * @var string
     */
    protected $_eventPrefix = 'maxcode_faq_question';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(QuestionResource::class);
    }

    /**
     * L'étiquette générale (page FAQ et widgets) et, pour chaque produit, catégorie ou page CMS où la question est
     * affectée, l'étiquette de cette entité (celle que porte le bloc de sa page). On y met aussi les affectations
     * d'avant l'enregistrement : une page dont la question vient de partir doit elle aussi être purgée.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $tags = [self::CACHE_TAG, self::CACHE_TAG . '_q_' . (int) $this->getId()];
        foreach (['assignments', 'previous_assignments'] as $key) {
            foreach ((array) $this->getData($key) as $type => $ids) {
                foreach ((array) $ids as $id) {
                    $tags[] = self::CACHE_TAG . '_' . $type . '_' . (int) $id;
                }
            }
        }

        return array_values(array_unique($tags));
    }
}
