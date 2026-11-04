<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model\Import;

use Maxcode\Faq\Model\Import\Mapper\MageprinceMapper;
use PHPUnit\Framework\TestCase;

class MageprinceMapperTest extends TestCase
{
    private MageprinceMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new MageprinceMapper();
    }

    /**
     * @param array<string, mixed> $overrides Colonnes de prince_faq à remplacer.
     * @return array<string, mixed>
     */
    private function question(array $overrides = []): array
    {
        return $overrides + [
            'faq_id' => 5, 'title' => 'Frais de port ?', 'content' => '<p>Réponse</p>', 'group' => '1',
            'storeview' => '0', 'customer_group' => null, 'sortorder' => 2, 'status' => 1,
        ];
    }

    private function raw(array $questions, array $groups = null): array
    {
        return [
            'groups' => $groups ?? [['faqgroup_id' => 1, 'groupname' => 'Livraison', 'storeview' => '0', 'sortorder' => 3, 'status' => 1]],
            'questions' => $questions,
        ];
    }

    public function testMapsAGroupAndAQuestion(): void
    {
        $result = $this->mapper->map($this->raw([$this->question()]), [1, 2], [0, 1]);

        $this->assertSame(
            [['source' => 'mageprince', 'ref' => '1', 'title' => 'Livraison', 'sort_order' => 3, 'is_active' => 1]],
            $result['groups']
        );
        $q = $result['questions'][0];
        $this->assertSame('mageprince', $q['source']);
        $this->assertSame('5', $q['ref']);
        $this->assertSame(1, $q['status']);
        $this->assertSame(2, $q['sort_order']);
        $this->assertSame(1, $q['show_on_page']);
        $this->assertSame([0 => ['question' => 'Frais de port ?', 'answer' => '<p>Réponse</p>']], $q['texts']);
        $this->assertSame([0], $q['store_ids']);
        $this->assertSame([], $q['customer_group_ids']);
        $this->assertSame(['1'], $q['group_refs']);
        $this->assertSame([], $result['warnings']);
    }

    public function testListsInCommaSeparatedColumnsAreSplit(): void
    {
        $result = $this->mapper->map(
            $this->raw([$this->question(['storeview' => '1,2', 'customer_group' => '0,3', 'group' => '1,2'])], [
                ['faqgroup_id' => 1, 'groupname' => 'A', 'storeview' => '', 'sortorder' => 0, 'status' => 1],
                ['faqgroup_id' => 2, 'groupname' => 'B', 'storeview' => null, 'sortorder' => 0, 'status' => 0],
            ]),
            [1, 2, 3],
            [0, 1, 3, 4]
        );
        $q = $result['questions'][0];

        $this->assertSame([1, 2], $q['store_ids']);
        $this->assertSame([0, 3], $q['customer_group_ids']);
        $this->assertSame(['1', '2'], $q['group_refs']);
        $this->assertSame(0, $result['groups'][1]['is_active']);
    }

    public function testStoreViewsMissingOnThisSiteAreIgnoredWhenSomeRemain(): void
    {
        $q = $this->mapper->map($this->raw([$this->question(['storeview' => '1,8,9'])]), [1, 2], [0])['questions'][0];

        $this->assertSame([1], $q['store_ids']);
        $this->assertSame(1, $q['status']);
    }

    public function testQuestionWithNoExistingStoreViewIsImportedAsDraft(): void
    {
        $result = $this->mapper->map($this->raw([$this->question(['storeview' => '6'])]), [1, 2], [0]);

        $this->assertSame(0, $result['questions'][0]['status']);
        $this->assertSame([0], $result['questions'][0]['store_ids']);
        $this->assertStringContainsString('draft', $result['warnings'][0]);
        $this->assertStringContainsString('store views (6)', $result['warnings'][0]);
    }

    public function testCustomerGroupsCoveringEveryGroupMeanEveryone(): void
    {
        $q = $this->mapper->map($this->raw([$this->question(['customer_group' => '0,1,3,9'])]), [1], [0, 1, 3])['questions'][0];

        $this->assertSame([], $q['customer_group_ids']);
        $this->assertSame(1, $q['status']);
    }

    public function testUnknownCustomerGroupsOnlyMakeADraft(): void
    {
        $result = $this->mapper->map($this->raw([$this->question(['customer_group' => '9,10'])]), [1], [0, 1]);

        $this->assertSame(0, $result['questions'][0]['status']);
        $this->assertSame([], $result['questions'][0]['customer_group_ids']);
    }

    public function testDisabledQuestionStaysADraft(): void
    {
        $this->assertSame(0, $this->mapper->map($this->raw([$this->question(['status' => 0])]), [1], [0])['questions'][0]['status']);
    }

    public function testQuestionWithoutGroupHasNoGroup(): void
    {
        $q = $this->mapper->map($this->raw([$this->question(['group' => null])]), [1], [0])['questions'][0];

        $this->assertSame([], $q['group_refs']);
    }

    public function testUnknownGroupReferenceIsWarnedAndDropped(): void
    {
        $result = $this->mapper->map($this->raw([$this->question(['group' => '1,99'])]), [1], [0]);

        $this->assertSame(['1'], $result['questions'][0]['group_refs']);
        $this->assertStringContainsString('group #99', $result['warnings'][0]);
    }

    public function testHtmlInTitlesBecomesPlainText(): void
    {
        $result = $this->mapper->map(
            $this->raw([$this->question(['title' => '<p>Qu&#039;est-ce   que <b>c\'est</b> ?</p>'])], [
                ['faqgroup_id' => 1, 'groupname' => ' <i>Groupe</i> ', 'storeview' => '0', 'sortorder' => 0, 'status' => 1],
            ]),
            [1],
            [0]
        );

        $this->assertSame("Qu'est-ce que c'est ?", $result['questions'][0]['texts'][0]['question']);
        $this->assertSame('Groupe', $result['groups'][0]['title']);
    }

    public function testRowsWithoutTextAreSkipped(): void
    {
        $result = $this->mapper->map($this->raw([$this->question(['title' => '  '])], [
            ['faqgroup_id' => 1, 'groupname' => '', 'storeview' => '0', 'sortorder' => 0, 'status' => 1],
        ]), [1], [0]);

        $this->assertSame([], $result['questions']);
        $this->assertSame([], $result['groups']);
        $this->assertCount(2, $result['warnings']);
    }

    public function testTheCustomerGroupRestrictionOfTheGroupIsAppliedToItsQuestions(): void
    {
        $groups = [['faqgroup_id' => 1, 'groupname' => 'Tarifs', 'storeview' => '0', 'customer_group' => '6', 'sortorder' => 0, 'status' => 1]];
        $q = $this->mapper->map($this->raw([$this->question(['customer_group' => null])], $groups), [1], [0, 1, 6])['questions'][0];

        $this->assertSame([6], $q['customer_group_ids']);
        $this->assertSame(1, $q['status']);
    }

    public function testTheQuestionAndTheGroupRestrictionsAreIntersected(): void
    {
        $groups = [['faqgroup_id' => 1, 'groupname' => 'A', 'storeview' => '1', 'customer_group' => '1,3', 'sortorder' => 0, 'status' => 1]];
        $q = $this->mapper->map(
            $this->raw([$this->question(['storeview' => '1,2', 'customer_group' => '3,4'])], $groups),
            [1, 2],
            [0, 1, 3, 4]
        )['questions'][0];

        $this->assertSame([1], $q['store_ids']);
        $this->assertSame([3], $q['customer_group_ids']);
        $this->assertSame(1, $q['status']);
    }

    public function testRestrictionsThatDoNotOverlapMakeADraft(): void
    {
        $groups = [['faqgroup_id' => 1, 'groupname' => 'A', 'storeview' => '2', 'customer_group' => null, 'sortorder' => 0, 'status' => 1]];
        $result = $this->mapper->map($this->raw([$this->question(['storeview' => '1'])], $groups), [1, 2], [0]);

        $this->assertSame(0, $result['questions'][0]['status']);
        $this->assertStringContainsString('group restrictions', $result['warnings'][0]);
    }

    public function testAQuestionWhoseGroupsAreAllDisabledIsADraft(): void
    {
        $groups = [['faqgroup_id' => 1, 'groupname' => 'A', 'storeview' => '0', 'customer_group' => null, 'sortorder' => 0, 'status' => 0]];
        $result = $this->mapper->map($this->raw([$this->question()], $groups), [1], [0]);

        $this->assertSame(0, $result['questions'][0]['status']);
        $this->assertStringContainsString('all its groups are disabled', $result['warnings'][0]);
        $this->assertSame(['1'], $result['questions'][0]['group_refs']);
    }

    public function testOneActiveGroupIsEnoughWhenOthersAreDisabled(): void
    {
        $groups = [
            ['faqgroup_id' => 1, 'groupname' => 'A', 'storeview' => '0', 'customer_group' => null, 'sortorder' => 0, 'status' => 0],
            ['faqgroup_id' => 2, 'groupname' => 'B', 'storeview' => '0', 'customer_group' => null, 'sortorder' => 0, 'status' => 1],
        ];
        $q = $this->mapper->map($this->raw([$this->question(['group' => '1,2'])], $groups), [1], [0])['questions'][0];

        $this->assertSame(1, $q['status']);
    }

    public function testGroupsWithDifferentRestrictionsCannotBeCombinedSoTheQuestionIsADraft(): void
    {
        $groups = [
            ['faqgroup_id' => 1, 'groupname' => 'A', 'storeview' => '0', 'customer_group' => '1', 'sortorder' => 0, 'status' => 1],
            ['faqgroup_id' => 2, 'groupname' => 'B', 'storeview' => '0', 'customer_group' => '3', 'sortorder' => 0, 'status' => 1],
        ];
        $result = $this->mapper->map($this->raw([$this->question(['group' => '1,2'])], $groups), [1], [0, 1, 3]);

        $this->assertSame(0, $result['questions'][0]['status']);
        $this->assertStringContainsString('different restrictions', $result['warnings'][0]);
    }

    public function testGroupsWithTheSameRestrictionsKeepTheQuestionActive(): void
    {
        $groups = [
            ['faqgroup_id' => 1, 'groupname' => 'A', 'storeview' => '0', 'customer_group' => '1', 'sortorder' => 0, 'status' => 1],
            ['faqgroup_id' => 2, 'groupname' => 'B', 'storeview' => '0', 'customer_group' => '1', 'sortorder' => 0, 'status' => 1],
        ];
        $q = $this->mapper->map($this->raw([$this->question(['group' => '1,2'])], $groups), [1], [0, 1, 3])['questions'][0];

        $this->assertSame(1, $q['status']);
        $this->assertSame([1], $q['customer_group_ids']);
    }

    public function testAnswerKeepsItsDirectivesUntouched(): void
    {
        $content = '<p>Voir {{widget type="Magento\\Cms\\Block\\Widget\\Page\\Link" page_id="16"}}</p>';
        $q = $this->mapper->map($this->raw([$this->question(['content' => $content])]), [1], [0])['questions'][0];

        $this->assertSame($content, $q['texts'][0]['answer']);
    }
}
