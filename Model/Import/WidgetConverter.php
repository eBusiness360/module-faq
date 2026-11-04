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
use Maxcode\Faq\Model\ResourceModel\Group as GroupResource;

/**
 * Remplace, dans les pages et blocs CMS, le widget de Mageprince FAQ par le widget « FAQ (Maxcode) », en traduisant les
 * numéros de groupes (les groupes doivent avoir été importés d'abord). Un widget dont un groupe est introuvable est
 * laissé tel quel et signalé.
 */
class WidgetConverter
{
    public const NEW_TYPE = 'Maxcode\\Faq\\Block\\Faq';
    private const TABLES = [
        'cms_block' => ['id' => 'block_id', 'label' => 'identifier'],
        'cms_page' => ['id' => 'page_id', 'label' => 'identifier'],
    ];

    /**
     * @param ResourceConnection $resource      Connexion à la base.
     * @param GroupResource      $groupResource Groupes de Maxcode_Faq.
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly GroupResource $groupResource
    ) {
    }

    /**
     * @param bool $dryRun Vrai : ne rien écrire.
     * @return array{found:int,converted:int,lines:string[],warnings:string[]}
     */
    public function convert(bool $dryRun): array
    {
        $report = ['found' => 0, 'converted' => 0, 'lines' => [], 'warnings' => []];
        $connection = $this->resource->getConnection();
        foreach (self::TABLES as $table => $columns) {
            $name = $this->resource->getTableName($table);
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($name, [$columns['id'], $columns['label'], 'content'])
                    ->where('content LIKE ?', '%Mageprince%')
            );
            foreach ($rows as $row) {
                $changes = 0;
                $content = $this->convertContent(
                    (string) $row['content'],
                    $changes,
                    $report,
                    $table . ' ' . $row[$columns['label']]
                );
                if ($changes > 0 && !$dryRun) {
                    $connection->update($name, ['content' => $content], [$columns['id'] . ' = ?' => $row[$columns['id']]]);
                }
            }
        }

        $this->reportWhatIsLeft($report);

        return $report;
    }

    /**
     * Ce que la commande ne réécrit pas : les instances de widget (Contenu > Widgets) et les widgets de MageWorx FAQ.
     *
     * @param array $report Rapport (complété).
     * @return void
     */
    private function reportWhatIsLeft(array &$report): void
    {
        $connection = $this->resource->getConnection();
        $instances = $this->resource->getTableName('widget_instance');
        if ($connection->isTableExists($instances)) {
            $count = (int) $connection->fetchOne(
                $connection->select()->from($instances, ['COUNT(*)'])
                    ->where('instance_type LIKE ?', '%Mageprince%Faq%')
            );
            if ($count > 0) {
                $report['warnings'][] = sprintf(
                    '%d widget instance(s) (Content > Widgets) use the Mageprince FAQ widget: replace them by hand.',
                    $count
                );
            }
        }
        $mageworx = 0;
        foreach (array_keys(self::TABLES) as $table) {
            $mageworx += (int) $connection->fetchOne(
                $connection->select()->from($this->resource->getTableName($table), ['COUNT(*)'])
                    ->where('content LIKE ?', '%MageWorx%Faq%')
            );
        }
        if ($mageworx > 0) {
            $report['warnings'][] = sprintf(
                '%d CMS page(s) or block(s) use a MageWorx FAQ widget: this command does not convert them, replace them by hand.',
                $mageworx
            );
        }
    }

    /**
     * @param string $content Contenu CMS.
     * @param int    $changes Nombre de widgets remplacés (complété).
     * @param array  $report  Rapport (complété).
     * @param string $where   Nom de la page ou du bloc, pour le rapport.
     * @return string Le contenu converti.
     */
    public function convertContent(string $content, int &$changes, array &$report, string $where): string
    {
        return (string) preg_replace_callback(
            '~\{\{widget\s(.*?)\}\}~is',
            function (array $match) use (&$changes, &$report, $where): string {
                // Les directives enregistrées par l'éditeur ont parfois des guillemets échappés et des barres doublées.
                $inner = str_replace(['\\"', '\\\\'], ['"', '\\'], $match[1]);
                $params = $this->params($inner);
                if (!isset($params['type']) || !$this->isMageprinceType($params['type'])) {
                    return $match[0];
                }
                $report['found']++;
                $ids = [];
                foreach (explode(',', (string) ($params['group_id'] ?? '')) as $part) {
                    if (ctype_digit(trim($part))) {
                        $id = $this->groupResource->findIdBySource(MageprinceMapper::SOURCE, trim($part));
                        if ($id === null) {
                            $report['warnings'][] = sprintf(
                                '%s: Mageprince group #%s has not been imported, widget left unchanged.',
                                $where,
                                trim($part)
                            );

                            return $match[0];
                        }
                        $ids[] = $id;
                    }
                }
                if (!$ids) {
                    $report['warnings'][] = sprintf('%s: widget without group, left unchanged.', $where);

                    return $match[0];
                }
                $directive = '{{widget type="' . self::NEW_TYPE . '" group_ids="' . implode(',', $ids) . '"';
                if (isset($params['title']) && trim($params['title']) !== '') {
                    $directive .= ' title="' . str_replace('"', '', trim($params['title'])) . '"';
                }
                if (isset($params['show_group_title'])) {
                    $directive .= ' show_group_titles="' . ($params['show_group_title'] === '1' ? '1' : '0') . '"';
                }
                if (isset($params['enable_collapse_expand'])) {
                    $directive .= ' expand_all="' . ($params['enable_collapse_expand'] === '1' ? '1' : '0') . '"';
                }
                $directive .= '}}';
                $changes++;
                $report['converted']++;
                $report['lines'][] = $where . ': ' . $directive;
                // Même écriture que l'original : une directive posée dans un contenu JSON (Magezon Page Builder)
                // a ses guillemets échappés (\") et ses barres doublées ; la réécrire autrement casserait le JSON.
                if (str_contains($match[0], '\\"')) {
                    $directive = str_replace(['\\', '"'], ['\\\\', '\\"'], $directive);
                } elseif (str_contains($match[0], '\\\\')) {
                    $directive = str_replace('\\', '\\\\', $directive);
                }

                return $directive;
            },
            $content
        );
    }

    /**
     * @param string $type Type de widget lu.
     * @return bool
     */
    private function isMageprinceType(string $type): bool
    {
        return strtolower((string) preg_replace('~\\\\+~', '\\', $type)) === 'mageprince\\faq\\block\\index\\index';
    }

    /**
     * @param string $text Paramètres d'une directive (nom="valeur").
     * @return array<string, string>
     */
    private function params(string $text): array
    {
        $params = [];
        if (preg_match_all('~([a-z_]+)\s*=\s*"([^"]*)"~i', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $params[strtolower($match[1])] = $match[2];
            }
        }

        return $params;
    }
}
