<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Block;

use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Maxcode\Faq\Block\Faq;
use Maxcode\Faq\Model\Config\Settings;
use Maxcode\Faq\Model\Content\AnswerRenderer;
use Maxcode\Faq\Model\Content\JsonLd;
use Maxcode\Faq\Model\Frontend\FaqReader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FaqTest extends TestCase
{
    private Settings&MockObject $settings;
    private FaqReader&MockObject $reader;
    private AnswerRenderer&MockObject $renderer;
    private RequestInterface&MockObject $request;
    private CmsPage&MockObject $cmsPage;
    private Registry&MockObject $registry;
    private Context&MockObject $context;
    private StoreManagerInterface&MockObject $storeManager;
    private HttpContext&MockObject $httpContext;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(Settings::class);
        $this->settings->method('isEnabled')->willReturn(true);
        $this->settings->method('isJsonLdEnabled')->willReturn(true);
        $this->settings->method('showGroupTitles')->willReturn(true);
        $this->reader = $this->createMock(FaqReader::class);
        $this->renderer = $this->createMock(AnswerRenderer::class);
        $this->renderer->method('render')->willReturnCallback(static fn (string $a): string => '<p>' . $a . '</p>');
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->httpContext = $this->createMock(HttpContext::class);
        $this->httpContext->method('getValue')->willReturn(3);
        $this->request = $this->createMock(RequestInterface::class);
        $this->cmsPage = $this->createMock(CmsPage::class);
        $this->registry = $this->createMock(Registry::class);
        $this->context = $this->createMock(Context::class);
        $this->context->method('getRequest')->willReturn($this->request);
    }

    private function block(array $data): Faq
    {
        return new Faq(
            $this->context,
            $this->settings,
            $this->reader,
            $this->renderer,
            new JsonLd(),
            $this->storeManager,
            $this->httpContext,
            $this->cmsPage,
            $this->registry,
            $data
        );
    }

    private function currentProduct(int $id): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $this->registry->method('registry')->willReturnMap([['current_product', $product]]);
    }

    private function q(int $id, array $groupIds = [], string $text = ''): array
    {
        return ['id' => $id, 'question' => $text ?: 'Question ' . $id, 'answer' => 'r' . $id, 'group_ids' => $groupIds];
    }

    public function testPageListsGroupsInOrderThenQuestionsWithoutGroup(): void
    {
        $this->reader->expects($this->once())->method('questions')
            ->with(2, 3, ['on_page' => true])
            ->willReturn([$this->q(1, [20]), $this->q(2, [10]), $this->q(3), $this->q(4, [10, 20])]);
        $this->reader->method('groups')->with(2)->willReturn([
            10 => ['id' => 10, 'title' => 'Livraison'],
            20 => ['id' => 20, 'title' => 'Paiement'],
            30 => ['id' => 30, 'title' => 'Vide'],
        ]);

        $sections = $this->block(['mode' => 'page'])->getSections();

        $this->assertSame(['Livraison', 'Paiement', ''], array_column($sections, 'title'));
        $this->assertSame([2, 4], array_column($sections[0]['questions'], 'id'));
        $this->assertSame([1, 4], array_column($sections[1]['questions'], 'id'));
        $this->assertSame([3], array_column($sections[2]['questions'], 'id'));
        $this->assertSame('<p>r2</p>', $sections[0]['questions'][0]['answer']);
    }

    public function testQuestionInTwoGroupsIsListedOnceInTheJsonLd(): void
    {
        $this->reader->method('questions')->willReturn([$this->q(4, [10, 20]), $this->q(5, [10])]);
        $this->reader->method('groups')->willReturn([
            10 => ['id' => 10, 'title' => 'A'], 20 => ['id' => 20, 'title' => 'B'],
        ]);

        $json = json_decode($this->block(['mode' => 'page'])->getJsonLd(), true);

        $this->assertCount(2, $json['mainEntity']);
    }

    public function testOnlyOneBlockPerRequestWritesTheJsonLd(): void
    {
        $this->reader->method('questions')->willReturn([$this->q(1)]);
        $jsonLd = new JsonLd();
        $make = fn (): Faq => new Faq(
            $this->context,
            $this->settings,
            $this->reader,
            $this->renderer,
            $jsonLd,
            $this->storeManager,
            $this->httpContext,
            $this->cmsPage,
            $this->registry,
            ['mode' => 'entity', 'entity_type' => 'product']
        );
        $this->currentProduct(402);

        $this->assertNotSame('', $make()->getJsonLd());
        $this->assertSame('', $make()->getJsonLd());
    }

    public function testJsonLdSwitchedOffInTheSettings(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('isJsonLdEnabled')->willReturn(false);
        $this->reader->method('questions')->willReturn([$this->q(1)]);
        $this->currentProduct(402);
        $block = new Faq(
            $this->context,
            $settings,
            $this->reader,
            $this->renderer,
            new JsonLd(),
            $this->storeManager,
            $this->httpContext,
            $this->cmsPage,
            $this->registry,
            ['mode' => 'entity', 'entity_type' => 'product']
        );

        $this->assertSame('', $block->getJsonLd());
    }

    public function testEntityModeAsksForTheQuestionsAssignedToTheCurrentProduct(): void
    {
        $this->currentProduct(402);
        $this->reader->expects($this->once())->method('questions')
            ->with(2, 3, ['entity' => ['product', 402]])
            ->willReturn([$this->q(7)]);

        $sections = $this->block(['mode' => 'entity', 'entity_type' => 'product'])->getSections();

        $this->assertSame([['title' => '', 'questions' => [['id' => 7, 'question' => 'Question 7', 'answer' => '<p>r7</p>']]]], $sections);
    }

    public function testEntityModeUsesTheCmsPageNumber(): void
    {
        $this->cmsPage->method('getId')->willReturn('17');
        $this->reader->expects($this->once())->method('questions')
            ->with(2, 3, ['entity' => ['cms_page', 17]])->willReturn([]);

        $this->assertSame([], $this->block(['mode' => 'entity', 'entity_type' => 'cms_page'])->getSections());
    }

    public function testEntityModeWithoutAnEntityShowsNothing(): void
    {
        $this->reader->expects($this->never())->method('questions');

        $this->assertSame([], $this->block(['mode' => 'entity', 'entity_type' => 'category'])->getSections());
    }

    public function testWidgetWithGroupsHonoursTheTitleChoiceAndLimit(): void
    {
        $this->reader->expects($this->once())->method('questions')
            ->with(2, 3, ['group_ids' => [4, 6], 'question_ids' => [9], 'limit' => 5])
            ->willReturn([$this->q(1, [4]), $this->q(2, [6]), $this->q(9)]);

        $sections = $this->block([
            'group_ids' => '4, 6,x', 'question_ids' => '9', 'limit' => '5', 'show_group_titles' => '0',
        ])->getSections();

        $this->assertCount(1, $sections);
        $this->assertSame('', $sections[0]['title']);
        $this->assertSame([1, 2, 9], array_column($sections[0]['questions'], 'id'));
    }

    public function testWidgetShowsGroupTitlesFromTheSettingsByDefault(): void
    {
        $this->reader->method('questions')->willReturn([$this->q(1, [4])]);
        $this->reader->expects($this->once())->method('groups')->with(2, [4])
            ->willReturn([4 => ['id' => 4, 'title' => 'Livraison']]);

        $sections = $this->block(['group_ids' => '4', 'show_group_titles' => ''])->getSections();

        $this->assertSame('Livraison', $sections[0]['title']);
    }

    public function testWidgetWithNeitherGroupsNorQuestionsShowsNothing(): void
    {
        $this->reader->expects($this->never())->method('questions');

        $this->assertSame([], $this->block(['group_ids' => '', 'question_ids' => ' '])->getSections());
    }

    public function testQuestionsWithoutAnAnswerAfterRenderingAreLeftOut(): void
    {
        $renderer = $this->createMock(AnswerRenderer::class);
        $renderer->method('render')->willReturnCallback(static fn (string $a): string => $a === 'r1' ? '' : '<p>ok</p>');
        $this->reader->method('questions')->willReturn([$this->q(1), $this->q(2)]);
        $this->reader->method('groups')->willReturn([]);
        $block = new Faq(
            $this->context,
            $this->settings,
            $this->reader,
            $renderer,
            new JsonLd(),
            $this->storeManager,
            $this->httpContext,
            $this->cmsPage,
            $this->registry,
            ['mode' => 'page']
        );

        $this->assertSame([2], array_column($block->getSections()[0]['questions'], 'id'));
    }

    public function testQuestionTextIsKeptAsTypedAndEscapedByTheTemplate(): void
    {
        $this->reader->method('questions')->willReturn([$this->q(1, [], ' Délai < 5 jours ? ')]);
        $this->reader->method('groups')->willReturn([]);

        $this->assertSame('Délai < 5 jours ?', $this->block(['mode' => 'page'])->getSections()[0]['questions'][0]['question']);
    }

    public function testTitles(): void
    {
        $this->settings->method('getBlockTitle')->willReturn('Questions fréquentes');

        $this->assertSame('', $this->block(['mode' => 'page'])->getTitle());
        $this->assertSame('Questions fréquentes', $this->block(['mode' => 'entity'])->getTitle());
        $this->assertSame('Ma zone', $this->block(['title' => ' Ma zone '])->getTitle());
        $this->assertSame(2, $this->block(['mode' => 'page'])->getGroupHeadingLevel());
        $this->assertSame(3, $this->block([])->getGroupHeadingLevel());
    }

    public function testGroupTabsNeedTwoTitledGroupsAndTheSetting(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('isEnabled')->willReturn(true);
        $settings->method('showGroupTabs')->willReturn(true);
        $this->reader->method('questions')->willReturn([$this->q(1, [10]), $this->q(2, [20])]);
        $this->reader->method('groups')->willReturn([
            10 => ['id' => 10, 'title' => 'A'], 20 => ['id' => 20, 'title' => 'B'],
        ]);
        $make = fn (array $data): Faq => new Faq(
            $this->context,
            $settings,
            $this->reader,
            $this->renderer,
            new JsonLd(),
            $this->storeManager,
            $this->httpContext,
            $this->cmsPage,
            $this->registry,
            $data
        );

        $this->assertTrue($make(['mode' => 'page'])->showGroupTabs());
        $this->assertFalse($make(['mode' => 'page', 'group_tabs' => '0'])->showGroupTabs());
        $this->assertTrue($make(['mode' => 'page', 'group_tabs' => ''])->showGroupTabs());
    }

    public function testGroupTabsNeverShowWithASingleGroup(): void
    {
        $this->settings->method('showGroupTabs')->willReturn(true);
        $this->reader->method('questions')->willReturn([$this->q(1, [10])]);
        $this->reader->method('groups')->willReturn([10 => ['id' => 10, 'title' => 'A']]);

        $this->assertFalse($this->block(['mode' => 'page'])->showGroupTabs());
    }

    public function testBlocksCarryTheCacheTag(): void
    {
        $this->assertSame(['maxcode_faq'], $this->block([])->getIdentities());
        $this->assertSame(['maxcode_faq'], $this->block(['mode' => 'page'])->getIdentities());
    }

    public function testEntityBlocksCarryTheTagOfTheirEntityOnly(): void
    {
        $this->currentProduct(402);
        $this->cmsPage->method('getId')->willReturn('17');

        $this->assertSame(
            ['maxcode_faq_product_402'],
            $this->block(['mode' => 'entity', 'entity_type' => 'product'])->getIdentities()
        );
        $this->assertSame(
            ['maxcode_faq_cms_page_17'],
            $this->block(['mode' => 'entity', 'entity_type' => 'cms_page'])->getIdentities()
        );
        $this->assertSame([], $this->block(['mode' => 'entity', 'entity_type' => 'category'])->getIdentities());
    }

    public function testTheProductIsReadFromTheRegistryNotFromTheAddress(): void
    {
        // Sur la page de modification d'une ligne du panier, « id » est le numéro de la ligne, pas du produit.
        $this->request->method('getParam')->with('id')->willReturn('123');
        $this->currentProduct(402);
        $this->reader->expects($this->once())->method('questions')
            ->with(2, 3, ['entity' => ['product', 402]])->willReturn([]);

        $this->block(['mode' => 'entity', 'entity_type' => 'product'])->getSections();
    }

    public function testHtmlIsEmptyWhenTheFaqIsDisabledOrOutOfPlace(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('isEnabled')->willReturn(true);
        $settings->method('isOnProduct')->willReturn(false);
        $block = new Faq(
            $this->context,
            $settings,
            $this->reader,
            $this->renderer,
            new JsonLd(),
            $this->storeManager,
            $this->httpContext,
            $this->cmsPage,
            $this->registry,
            ['mode' => 'entity', 'entity_type' => 'product']
        );
        $method = new \ReflectionMethod($block, '_toHtml');

        $this->assertSame('', $method->invoke($block));
    }
}
