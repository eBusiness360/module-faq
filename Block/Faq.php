<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Block;

use Magento\Cms\Model\Page as CmsPage;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use Maxcode\Faq\Model\Config\Settings;
use Maxcode\Faq\Model\Content\AnswerRenderer;
use Maxcode\Faq\Model\Content\JsonLd;
use Maxcode\Faq\Model\Frontend\FaqReader;
use Maxcode\Faq\Model\Question;

/**
 * Bloc FAQ, un seul pour tous les emplacements :
 * - mode « page » : la page FAQ (toutes les questions « affichées sur la page », par groupe) ;
 * - mode « entity » : les questions affectées à la fiche produit, la catégorie ou la page CMS courante ;
 * - sans mode : le widget « FAQ (Maxcode) » (groupes et/ou numéros de questions choisis dans le widget).
 *
 * Le balisage ne dépend que de la vue de magasin et du groupe de clients du visiteur, que le cache de pages distingue
 * déjà : la page reste cachable. Il porte l'étiquette de cache de la FAQ, vidée à chaque modification.
 */
class Faq extends Template implements IdentityInterface, BlockInterface
{
    public const MODE_PAGE = 'page';
    public const MODE_ENTITY = 'entity';

    /** @var int Compteur pour des identifiants HTML uniques quand une page porte plusieurs blocs. */
    private static int $counter = 0;
    private static bool $scriptEmitted = false;
    /** @var array<int, bool> Ancres déjà écrites pendant la requête. */
    private static array $anchors = [];

    /**
     * @var string
     */
    protected $_template = 'Maxcode_Faq::faq.phtml';

    private ?array $sections = null;
    private int $instance;

    /**
     * @param Context               $context      Contexte du bloc.
     * @param Settings              $settings     Réglages.
     * @param FaqReader             $reader       Lecture des questions visibles.
     * @param AnswerRenderer        $renderer     Préparation des réponses.
     * @param JsonLd                $jsonLd       Balisage FAQPage.
     * @param StoreManagerInterface $storeManager Vues de magasin.
     * @param HttpContext           $httpContext  Contexte du visiteur (groupe de clients).
     * @param CmsPage               $cmsPage      Page CMS courante.
     * @param Registry              $registry     Produit et catégorie de la page courante.
     * @param array                 $data         Données du bloc (arguments du layout, paramètres du widget).
     */
    public function __construct(
        Context $context,
        private readonly Settings $settings,
        private readonly FaqReader $reader,
        private readonly AnswerRenderer $renderer,
        private readonly JsonLd $jsonLd,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpContext $httpContext,
        private readonly CmsPage $cmsPage,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->instance = ++self::$counter;
    }

    /**
     * La page FAQ et les widgets portent l'étiquette générale ; le bloc d'une fiche produit, d'une catégorie ou d'une
     * page CMS porte celle de son entité seulement, pour qu'enregistrer une question ne purge que les pages concernées
     * (voir Question::getIdentities).
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        if ($this->getData('mode') === self::MODE_ENTITY) {
            $type = (string) $this->getData('entity_type');
            $id = $this->currentEntityId($type);

            return $id > 0 ? [Question::CACHE_TAG . '_' . $type . '_' . $id] : [];
        }

        return [Question::CACHE_TAG];
    }

    /**
     * @return string Identifiant HTML unique du bloc.
     */
    public function getDomId(): string
    {
        return 'mcfaq-' . $this->instance;
    }

    /**
     * @return string Titre du bloc (vide : aucun).
     */
    public function getTitle(): string
    {
        $mode = (string) $this->getData('mode');
        if ($mode === self::MODE_PAGE) {
            return '';
        }
        $own = trim((string) $this->getData('title'));

        return $own !== '' ? $own : ($mode === self::MODE_ENTITY ? $this->settings->getBlockTitle($this->storeId()) : '');
    }

    /**
     * @return int Niveau des titres de groupe (h2 sur la page FAQ, dont le titre est le h1 ; h3 ailleurs).
     */
    public function getGroupHeadingLevel(): int
    {
        return $this->getData('mode') === self::MODE_PAGE ? 2 : 3;
    }

    /**
     * @return bool Faut-il le bouton « tout ouvrir » ?
     */
    public function showExpandAll(): bool
    {
        $own = $this->getData('expand_all');
        $wanted = $own === null || $own === '' ? $this->settings->showExpandAll($this->storeId()) : (bool) (int) $own;

        return $wanted && $this->countQuestions() > 1;
    }

    /**
     * @return bool Faut-il les filtres par groupe (pastilles) ? Seulement quand au moins deux groupes ont un titre.
     */
    public function showGroupTabs(): bool
    {
        $own = $this->getData('group_tabs');
        $wanted = $own === null || $own === '' ? $this->settings->showGroupTabs($this->storeId()) : (bool) (int) $own;
        $titled = 0;
        foreach ($this->getSections() as $section) {
            $titled += $section['title'] !== '' ? 1 : 0;
        }

        return $wanted && $titled > 1;
    }

    /**
     * Une ancre HTML (id) ne doit exister qu'une fois par page : une question affichée dans deux groupes, ou dans deux
     * blocs, ne la porte que la première fois.
     *
     * @param int $questionId Question.
     * @return bool Vrai pour la première fois de la requête.
     */
    public function claimAnchor(int $questionId): bool
    {
        if (isset(self::$anchors[$questionId])) {
            return false;
        }
        self::$anchors[$questionId] = true;

        return true;
    }

    /**
     * @return bool Faut-il émettre le script (une seule fois par page) ?
     */
    public function claimScript(): bool
    {
        if (self::$scriptEmitted) {
            return false;
        }
        self::$scriptEmitted = true;

        return true;
    }

    /**
     * @return list<array{title:string,questions:list<array{id:int,question:string,answer:string}>}>
     */
    public function getSections(): array
    {
        if ($this->sections === null) {
            $this->sections = $this->buildSections();
        }

        return $this->sections;
    }

    /**
     * @return string Le JSON-LD FAQPage, s'il est activé, non déjà écrit sur la page, et s'il y a des questions.
     */
    public function getJsonLd(): string
    {
        if (!$this->settings->isJsonLdEnabled($this->storeId())) {
            return '';
        }
        $items = [];
        foreach ($this->getSections() as $section) {
            foreach ($section['questions'] as $question) {
                $items[$question['id']] = ['question' => $question['question'], 'answer' => $question['answer']];
            }
        }
        $json = $this->jsonLd->build(array_values($items));

        return $json !== '' && $this->jsonLd->claim() ? $json : '';
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        $storeId = $this->storeId();
        if (!$this->settings->isEnabled($storeId) || !$this->isAllowedHere($storeId) || !$this->getSections()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * @param int $storeId Vue de magasin.
     * @return bool Cet emplacement est-il activé dans les réglages ?
     */
    private function isAllowedHere(int $storeId): bool
    {
        if ($this->getData('mode') !== self::MODE_ENTITY) {
            return true;
        }

        return match ((string) $this->getData('entity_type')) {
            'product' => $this->settings->isOnProduct($storeId),
            'category' => $this->settings->isOnCategory($storeId),
            'cms_page' => $this->settings->isOnCms($storeId),
            default => false,
        };
    }

    /**
     * @return array
     */
    private function buildSections(): array
    {
        $storeId = $this->storeId();
        $customerGroup = (int) $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
        $mode = (string) $this->getData('mode');

        if ($mode === self::MODE_ENTITY) {
            $entityId = $this->currentEntityId((string) $this->getData('entity_type'));
            if ($entityId === 0) {
                return [];
            }
            $questions = $this->reader->questions(
                $storeId,
                $customerGroup,
                ['entity' => [(string) $this->getData('entity_type'), $entityId]]
            );

            return $this->flat($questions, $storeId);
        }
        if ($mode === self::MODE_PAGE) {
            $questions = $this->reader->questions($storeId, $customerGroup, ['on_page' => true]);

            return $this->grouped($questions, $this->reader->groups($storeId), $storeId, true);
        }

        $groupIds = $this->numbers((string) $this->getData('group_ids'));
        $questionIds = $this->numbers((string) $this->getData('question_ids'));
        if (!$groupIds && !$questionIds) {
            return [];
        }
        $limit = (int) $this->getData('limit');
        $questions = $this->reader->questions(
            $storeId,
            $customerGroup,
            ['group_ids' => $groupIds, 'question_ids' => $questionIds, 'limit' => $limit > 0 ? $limit : FaqReader::MAX_QUESTIONS]
        );
        $own = $this->getData('show_group_titles');
        $titles = $own === null || $own === '' ? $this->settings->showGroupTitles($storeId) : (bool) (int) $own;

        return $groupIds
            ? $this->grouped($questions, $this->reader->groups($storeId, $groupIds), $storeId, $titles)
            : $this->flat($questions, $storeId);
    }

    /**
     * Sections par groupe, dans l'ordre des groupes ; les questions sans groupe retenu forment une dernière section
     * sans titre. Sans titres de groupe, tout tient en une section.
     *
     * @param list<array{id:int,question:string,answer:string,group_ids:int[]}> $questions Questions.
     * @param array<int, array{id:int,title:string}>                            $groups    Groupes à montrer.
     * @param int                                                               $storeId   Vue de magasin.
     * @param bool                                                              $titles    Montrer les titres de groupe ?
     * @return array
     */
    private function grouped(array $questions, array $groups, int $storeId, bool $titles): array
    {
        if (!$titles) {
            return $this->flat($questions, $storeId);
        }
        $sections = [];
        $placed = [];
        foreach ($groups as $group) {
            $inGroup = array_values(array_filter(
                $questions,
                static fn (array $q): bool => in_array($group['id'], $q['group_ids'], true)
            ));
            foreach ($inGroup as $question) {
                $placed[$question['id']] = true;
            }
            $items = $this->prepare($inGroup, $storeId);
            if ($items) {
                $sections[] = ['title' => $group['title'], 'questions' => $items];
            }
        }
        $rest = array_values(array_filter($questions, static fn (array $q): bool => !isset($placed[$q['id']])));
        $items = $this->prepare($rest, $storeId);
        if ($items) {
            $sections[] = ['title' => '', 'questions' => $items];
        }

        return $sections;
    }

    /**
     * @param list<array{id:int,question:string,answer:string,group_ids:int[]}> $questions Questions.
     * @param int                                                               $storeId   Vue de magasin.
     * @return array
     */
    private function flat(array $questions, int $storeId): array
    {
        $items = $this->prepare($questions, $storeId);

        return $items ? [['title' => '', 'questions' => $items]] : [];
    }

    /**
     * @param list<array{id:int,question:string,answer:string,group_ids:int[]}> $questions Questions.
     * @param int                                                               $storeId   Vue de magasin.
     * @return list<array{id:int,question:string,answer:string}> Réponses prêtes à l'affichage ; sans réponse : écartées.
     */
    private function prepare(array $questions, int $storeId): array
    {
        $items = [];
        foreach ($questions as $question) {
            $answer = $this->renderer->render($question['answer'], $storeId);
            // Le texte d'une question est affiché échappé (jamais interprété) : on ne retire donc pas de balises, ce qui
            // abîmerait « < 5 min et > 2 min ».
            $text = trim($question['question']);
            if ($answer !== '' && $text !== '') {
                $items[] = ['id' => $question['id'], 'question' => $text, 'answer' => $answer];
            }
        }

        return $items;
    }

    /**
     * @return int
     */
    private function countQuestions(): int
    {
        $count = 0;
        foreach ($this->getSections() as $section) {
            $count += count($section['questions']);
        }

        return $count;
    }

    /**
     * @param string $type product, category ou cms_page.
     * @return int Identifiant de l'entité de la page courante (0 : aucune).
     */
    private function currentEntityId(string $type): int
    {
        // Le paramètre « id » de l'adresse n'est pas fiable : sur la page de modification d'une ligne du panier ou de la
        // liste d'envies (même présentation que la fiche produit), c'est le numéro de la ligne.
        return match ($type) {
            'product' => (int) $this->registry->registry('current_product')?->getId(),
            'category' => (int) $this->registry->registry('current_category')?->getId(),
            'cms_page' => (int) $this->cmsPage->getId(),
            default => 0,
        };
    }

    /**
     * @param string $list Numéros séparés par des virgules.
     * @return int[]
     */
    private function numbers(string $list): array
    {
        $numbers = [];
        foreach (explode(',', $list) as $part) {
            $part = trim($part);
            if (ctype_digit($part) && (int) $part > 0) {
                $numbers[(int) $part] = (int) $part;
            }
        }

        return array_values($numbers);
    }

    /**
     * @return int
     */
    private function storeId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
