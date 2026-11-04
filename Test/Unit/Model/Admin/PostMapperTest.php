<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Admin;

use Maxcode\Faq\Model\Admin\PostMapper;
use Maxcode\Faq\Model\Admin\ProductSkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PostMapperTest extends TestCase
{
    private ProductSkus&MockObject $skus;
    private PostMapper $mapper;

    protected function setUp(): void
    {
        $this->skus = $this->createMock(ProductSkus::class);
        $this->skus->method('parse')->willReturnCallback(
            static fn (string $text): array => array_values(array_filter(array_map('trim', explode("\n", $text))))
        );
        $this->skus->method('resolve')->willReturnCallback(
            static fn (array $skus): array => [
                'ids' => array_values(array_map(static fn (string $s): int => (int) substr($s, 1), array_filter($skus, static fn (string $s): bool => $s[0] === 'P'))),
                'unknown' => array_values(array_filter($skus, static fn (string $s): bool => $s[0] !== 'P')),
            ]
        );
        $this->mapper = new PostMapper($this->skus);
    }

    public function testQuestionPostBecomesModelData(): void
    {
        $result = $this->mapper->question([
            'status' => '1', 'sort_order' => '5', 'show_on_page' => 1,
            'question' => "  Frais\n de   port < 5 jours ? ", 'answer' => '<p>Gratuits</p>',
            'store_ids' => ['1', '2'], 'group_ids' => ['3', '3', 'x'], 'customer_group_ids' => ['0'],
            'category_ids' => ['10'], 'cms_page_ids' => ['17'], 'product_skus' => "P402\nZZ9",
        ]);
        $data = $result['data'];

        $this->assertSame(1, $data['status']);
        $this->assertSame(5, $data['sort_order']);
        $this->assertSame(1, $data['show_on_page']);
        $this->assertSame('Frais de port < 5 jours ?', $data['texts'][0]['question']);
        $this->assertSame('<p>Gratuits</p>', $data['texts'][0]['answer']);
        $this->assertSame([1, 2], $data['store_ids']);
        $this->assertSame([3], $data['group_ids']);
        $this->assertSame([0], $data['customer_group_ids']);
        $this->assertSame(['product' => [402], 'category' => [10], 'cms_page' => [17]], $data['assignments']);
        $this->assertSame(['ZZ9'], $result['unknown_skus']);
    }

    public function testAllStoreViewsWinsOverSpecificOnes(): void
    {
        $this->assertSame([0], $this->mapper->question(['store_ids' => ['2', '0']])['data']['store_ids']);
        $this->assertSame([0], $this->mapper->question([])['data']['store_ids']);
    }

    public function testTranslationsAreKeptPerStoreAndDeletedRowsDropped(): void
    {
        $data = $this->mapper->question([
            'question' => 'FR', 'answer' => 'r',
            'translations' => [
                ['store_id' => '2', 'question' => 'EN', 'answer' => 'a'],
                ['store_id' => '3', 'question' => 'DE', 'answer' => 'b', 'delete' => '1'],
                ['store_id' => '0', 'question' => 'ignoré', 'answer' => 'c'],
                'pas un tableau',
            ],
        ])['data'];

        $this->assertSame([0, 2], array_keys($data['texts']));
        $this->assertSame('EN', $data['texts'][2]['question']);
    }

    public function testStatusIsEitherActiveOrDraft(): void
    {
        $this->assertSame(0, $this->mapper->question(['status' => '7'])['data']['status']);
        $this->assertSame(0, $this->mapper->question([])['data']['status']);
    }

    public function testUncheckedToggleMeansNotOnThePage(): void
    {
        $this->assertSame(0, $this->mapper->question(['show_on_page' => '0'])['data']['show_on_page']);
        $this->assertSame(0, $this->mapper->question([])['data']['show_on_page']);
    }

    public function testGroupPost(): void
    {
        $data = $this->mapper->group([
            'is_active' => '1', 'sort_order' => '2', 'title' => ' Livraison ',
            'translations' => [['store_id' => '2', 'title' => 'Delivery'], ['store_id' => '3', 'title' => 'x', 'delete' => 1]],
        ]);

        $this->assertSame(1, $data['is_active']);
        $this->assertSame(2, $data['sort_order']);
        $this->assertSame([0 => 'Livraison', 2 => 'Delivery'], $data['texts']);
    }
}
