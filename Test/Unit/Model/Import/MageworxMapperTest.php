<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Import;

use Maxcode\Faq\Model\Import\Mapper\MageworxMapper;
use PHPUnit\Framework\TestCase;

class MageworxMapperTest extends TestCase
{
    private MageworxMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new MageworxMapper();
    }

    /**
     * Un bloc personnalisé (n° 1) avec deux questions, un bloc produit (n° 2) qui en réutilise une.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function raw(): array
    {
        return [
            'blocks' => [
                ['block_id' => 1, 'type' => 1, 'identifier' => 'livraison', 'is_active' => 1, 'sort_order' => 4],
                ['block_id' => 2, 'type' => 2, 'identifier' => 'produit', 'is_active' => 1, 'sort_order' => 0],
            ],
            'block_values' => [
                ['block_id' => 1, 'store_id' => 0, 'title' => 'Livraison'],
                ['block_id' => 1, 'store_id' => 2, 'title' => 'Delivery'],
                ['block_id' => 1, 'store_id' => 9, 'title' => 'Absent du site'],
            ],
            'block_stores' => [['block_id' => 1, 'store_id' => 1], ['block_id' => 1, 'store_id' => 2]],
            'items' => [
                ['block_id' => 1, 'question_id' => 10, 'answer_id' => 20, 'sort_order' => 1, 'is_active' => 1],
                ['block_id' => 1, 'question_id' => 11, 'answer_id' => 21, 'sort_order' => 2, 'is_active' => 1],
                ['block_id' => 2, 'question_id' => 10, 'answer_id' => 20, 'sort_order' => 5, 'is_active' => 1],
                ['block_id' => 2, 'question_id' => 12, 'answer_id' => 22, 'sort_order' => 6, 'is_active' => 1],
            ],
            'questions' => [
                ['question_id' => 10, 'is_active' => 1], ['question_id' => 11, 'is_active' => 1],
                ['question_id' => 12, 'is_active' => 1],
            ],
            'question_values' => [
                ['question_id' => 10, 'store_id' => 0, 'title' => 'Délai ?'],
                ['question_id' => 10, 'store_id' => 2, 'title' => 'Delay?'],
                ['question_id' => 11, 'store_id' => 0, 'title' => 'Retour ?'],
                ['question_id' => 12, 'store_id' => 0, 'title' => 'Garantie du produit ?'],
            ],
            'answers' => [
                ['answer_id' => 20, 'is_active' => 1], ['answer_id' => 21, 'is_active' => 1],
                ['answer_id' => 22, 'is_active' => 1],
            ],
            'answer_values' => [
                ['answer_id' => 20, 'store_id' => 0, 'content' => '<p>48 h</p>'],
                ['answer_id' => 21, 'store_id' => 0, 'content' => '<p>14 jours</p>'],
                ['answer_id' => 22, 'store_id' => 0, 'content' => '<p>2 ans</p>'],
            ],
            'links' => [
                ['block_id' => 2, 'entity_type' => 'product', 'entity_id' => 402, 'entity_identifier' => null, 'store_id' => 0],
                ['block_id' => 2, 'entity_type' => 'category', 'entity_id' => 10, 'entity_identifier' => null, 'store_id' => 0],
                ['block_id' => 2, 'entity_type' => 'cms_page', 'entity_id' => null, 'entity_identifier' => 'contact', 'store_id' => 0],
                ['block_id' => 2, 'entity_type' => 'special', 'entity_id' => null, 'entity_identifier' => 'cart', 'store_id' => 0],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $questions Questions converties.
     * @param string                           $ref       Identifiant dans MageWorx.
     * @return array<string, mixed>
     */
    private function find(array $questions, string $ref): array
    {
        foreach ($questions as $question) {
            if ($question['ref'] === $ref) {
                return $question;
            }
        }
        $this->fail('Question ' . $ref . ' not found');
    }

    public function testCustomBlockBecomesAGroupWithTranslatedTitles(): void
    {
        $result = $this->mapper->map($this->raw(), [1, 2], ['contact' => 17]);

        $this->assertCount(1, $result['groups']);
        $group = $result['groups'][0];
        $this->assertSame('b1', $group['ref']);
        $this->assertSame('Livraison', $group['title']);
        $this->assertSame([2 => 'Delivery'], $group['translations']);
        $this->assertSame(4, $group['sort_order']);
    }

    public function testEachQuestionAnswerPairBecomesAQuestion(): void
    {
        $result = $this->mapper->map($this->raw(), [1, 2], ['contact' => 17]);

        $this->assertSame(['10-20', '11-21', '12-22'], array_column($result['questions'], 'ref'));
    }

    public function testPairInAGroupBlockIsOnThePageAndKeepsTheSmallestSortOrder(): void
    {
        $q = $this->find($this->mapper->map($this->raw(), [1, 2], ['contact' => 17])['questions'], '10-20');

        $this->assertSame(1, $q['show_on_page']);
        $this->assertSame(['b1'], $q['group_refs']);
        $this->assertSame(1, $q['sort_order']);
        $this->assertSame(1, $q['status']);
    }

    public function testPairOnlyInAProductBlockIsNotOnThePageButAssigned(): void
    {
        $q = $this->find($this->mapper->map($this->raw(), [1, 2], ['contact' => 17])['questions'], '12-22');

        $this->assertSame(0, $q['show_on_page']);
        $this->assertSame([], $q['group_refs']);
        $this->assertSame([402], $q['assignments']['product']);
        $this->assertSame([10], $q['assignments']['category']);
        $this->assertSame([17], $q['assignments']['cms_page']);
    }

    public function testSharedPairGetsTheAssignmentsOfEveryBlockAndKeepsItsGroup(): void
    {
        $q = $this->find($this->mapper->map($this->raw(), [1, 2], ['contact' => 17])['questions'], '10-20');

        $this->assertSame([402], $q['assignments']['product']);
        $this->assertSame(['b1'], $q['group_refs']);
    }

    public function testInactiveItemBecomesADraftAndWidensNothing(): void
    {
        $raw = $this->raw();
        $raw['items'][1]['is_active'] = 0;
        $q = $this->find($this->mapper->map($raw, [1, 2], [])['questions'], '11-21');

        $this->assertSame(0, $q['status']);
        $this->assertSame([], $q['group_refs']);
        $this->assertSame(0, $q['show_on_page']);
    }

    public function testAnInactiveBlockDoesNotPublishOrAssignItsQuestionsToo(): void
    {
        $raw = $this->raw();
        $raw['blocks'][1]['is_active'] = 0; // le bloc produit
        $q = $this->find($this->mapper->map($raw, [1, 2], ['contact' => 17])['questions'], '12-22');

        $this->assertSame(0, $q['status']);
        $this->assertSame(['product' => [], 'category' => [], 'cms_page' => []], $q['assignments']);
    }

    public function testASpecialBlockIsNotPublishedOnTheFaqPage(): void
    {
        $raw = $this->raw();
        $raw['blocks'][] = ['block_id' => 4, 'type' => 5, 'identifier' => 'panier', 'is_active' => 1, 'sort_order' => 0];
        $raw['block_values'][] = ['block_id' => 4, 'store_id' => 0, 'title' => 'Panier'];
        $raw['items'][] = ['block_id' => 4, 'question_id' => 12, 'answer_id' => 22, 'sort_order' => 9, 'is_active' => 1];
        $result = $this->mapper->map($raw, [1, 2], []);

        $this->assertCount(1, $result['groups']);
        $this->assertSame(0, $this->find($result['questions'], '12-22')['show_on_page']);
        $this->assertStringContainsString('1 MageWorx special block', $result['notes'][0]);
    }

    public function testStoreTextsFallBackToTheDefaultView(): void
    {
        $q = $this->find($this->mapper->map($this->raw(), [1, 2], [])['questions'], '10-20');

        $this->assertSame('Délai ?', $q['texts'][0]['question']);
        $this->assertSame('Delay?', $q['texts'][2]['question']);
        $this->assertSame('<p>48 h</p>', $q['texts'][2]['answer']);
        $this->assertArrayNotHasKey(1, $q['texts']);
    }

    public function testBlockStoreVisibilityBecomesTheQuestionStores(): void
    {
        $q = $this->find($this->mapper->map($this->raw(), [1, 2], [])['questions'], '11-21');

        $this->assertSame([1, 2], array_values($q['store_ids']));
    }

    public function testAProductBlockWithoutStoreRowsMeansEverywhere(): void
    {
        $q = $this->find($this->mapper->map($this->raw(), [1, 2], [])['questions'], '12-22');

        $this->assertSame([0], $q['store_ids']);
    }

    public function testSpecialLinksAreNotedNotCopied(): void
    {
        $result = $this->mapper->map($this->raw(), [1, 2], []);

        $this->assertStringContainsString('1 MageWorx link', $result['notes'][0]);
    }

    public function testCmsLinkWithUnknownPageIsDropped(): void
    {
        $q = $this->find($this->mapper->map($this->raw(), [1, 2], [])['questions'], '12-22');

        $this->assertSame([], $q['assignments']['cms_page']);
    }

    public function testPairWithoutDefaultTextIsSkipped(): void
    {
        $raw = $this->raw();
        $raw['question_values'] = array_values(array_filter(
            $raw['question_values'],
            static fn (array $v): bool => !($v['question_id'] === 12 && $v['store_id'] === 0)
        ));
        $result = $this->mapper->map($raw, [1, 2], []);

        $this->assertNotContains('12-22', array_column($result['questions'], 'ref'));
        $this->assertStringContainsString('12-22', $result['warnings'][0]);
    }

    public function testQuestionWithoutAnswerCannotBeActive(): void
    {
        $raw = $this->raw();
        $raw['answer_values'] = array_values(array_filter(
            $raw['answer_values'],
            static fn (array $v): bool => $v['answer_id'] !== 22
        ));
        $q = $this->find($this->mapper->map($raw, [1, 2], [])['questions'], '12-22');

        $this->assertSame(0, $q['status']);
    }
}
