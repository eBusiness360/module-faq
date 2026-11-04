<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Maxcode\Faq\Model\Import\Mapper\MageprinceMapper;
use Maxcode\Faq\Model\Import\Mapper\MageworxMapper;
use Maxcode\Faq\Model\Import\Reader\MageprinceReader;
use Maxcode\Faq\Model\Import\Reader\MageworxReader;

/**
 * Importe la FAQ de Mageprince FAQ et/ou de MageWorx FAQ dans Maxcode_Faq (voir maxcode:faq:import).
 */
class Importer
{
    public const SOURCE_MAGEPRINCE = 'mageprince';
    public const SOURCE_MAGEWORX = 'mageworx';

    /**
     * @param MageprinceReader   $mageprinceReader Lecture de Mageprince FAQ.
     * @param MageworxReader     $mageworxReader   Lecture de MageWorx FAQ.
     * @param MageprinceMapper   $mageprinceMapper Conversion de Mageprince FAQ.
     * @param MageworxMapper     $mageworxMapper   Conversion de MageWorx FAQ.
     * @param Writer             $writer           Écriture dans Maxcode_Faq.
     * @param WidgetConverter    $widgetConverter  Remplacement des widgets Mageprince dans le contenu CMS.
     * @param ResourceConnection $resource         Connexion à la base (vues de magasin, groupes de clients, pages).
     */
    public function __construct(
        private readonly MageprinceReader $mageprinceReader,
        private readonly MageworxReader $mageworxReader,
        private readonly MageprinceMapper $mageprinceMapper,
        private readonly MageworxMapper $mageworxMapper,
        private readonly Writer $writer,
        private readonly WidgetConverter $widgetConverter,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param string[] $sources        « mageprince » et/ou « mageworx ».
     * @param bool     $dryRun         Ne rien écrire, compter seulement.
     * @param bool     $convertWidgets Remplacer aussi les widgets Mageprince des pages et blocs CMS.
     * @return array{
     *   sources:array<string, array{available:bool,report:array<string, mixed>,warnings:string[],notes:string[]}>,
     *   widgets:array<string, mixed>|null,
     *   page_url:string
     * }
     */
    public function run(array $sources, bool $dryRun, bool $convertWidgets): array
    {
        $result = ['sources' => [], 'widgets' => null, 'page_url' => ''];
        foreach ($sources as $source) {
            if ($source === self::SOURCE_MAGEPRINCE) {
                $result['sources'][$source] = $this->mageprince($dryRun, $result['page_url']);
            } elseif ($source === self::SOURCE_MAGEWORX) {
                $result['sources'][$source] = $this->mageworx($dryRun);
            }
        }
        if ($convertWidgets) {
            $result['widgets'] = $this->widgetConverter->convert($dryRun);
        }

        return $result;
    }

    /**
     * @param bool   $dryRun  Ne rien écrire.
     * @param string $pageUrl Adresse de la page FAQ de Mageprince (complétée).
     * @return array{available:bool,report:array<string, mixed>,warnings:string[],notes:string[]}
     */
    private function mageprince(bool $dryRun, string &$pageUrl): array
    {
        if (!$this->mageprinceReader->isAvailable()) {
            return ['available' => false, 'report' => [], 'warnings' => [], 'notes' => []];
        }
        $pageUrl = $this->mageprinceReader->pageUrlKey();
        $mapped = $this->mageprinceMapper->map(
            $this->mageprinceReader->read(),
            $this->storeIds(),
            $this->customerGroupIds()
        );

        return $this->finish($mapped, $dryRun);
    }

    /**
     * @param bool $dryRun Ne rien écrire.
     * @return array{available:bool,report:array<string, mixed>,warnings:string[],notes:string[]}
     */
    private function mageworx(bool $dryRun): array
    {
        if (!$this->mageworxReader->isAvailable()) {
            return ['available' => false, 'report' => [], 'warnings' => [], 'notes' => []];
        }
        $mapped = $this->mageworxMapper->map($this->mageworxReader->read(), $this->storeIds(), $this->cmsPages());

        return $this->finish($mapped, $dryRun);
    }

    /**
     * @param array $mapped Données préparées par un convertisseur.
     * @param bool  $dryRun Ne rien écrire.
     * @return array{available:bool,report:array<string, mixed>,warnings:string[],notes:string[]}
     */
    private function finish(array $mapped, bool $dryRun): array
    {
        $report = $this->writer->write($mapped, $dryRun);

        return [
            'available' => true,
            'report' => $report,
            'warnings' => array_merge($mapped['warnings'], $report['warnings']),
            'notes' => $mapped['notes'],
        ];
    }

    /**
     * @return int[] Vues de magasin du site (sans la vue 0).
     */
    private function storeIds(): array
    {
        $connection = $this->resource->getConnection();

        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->resource->getTableName('store'), ['store_id'])->where('store_id > 0')
        ));
    }

    /**
     * @return int[] Groupes de clients du site.
     */
    private function customerGroupIds(): array
    {
        $connection = $this->resource->getConnection();

        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->resource->getTableName('customer_group'), ['customer_group_id'])
        ));
    }

    /**
     * @return array<string, int> Pages CMS : identifiant => numéro.
     */
    private function cmsPages(): array
    {
        $connection = $this->resource->getConnection();
        $pages = [];
        foreach ($connection->fetchPairs(
            $connection->select()->from($this->resource->getTableName('cms_page'), ['identifier', 'page_id'])
        ) as $identifier => $id) {
            $pages[(string) $identifier] = (int) $id;
        }

        return $pages;
    }
}
