<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Maxcode\Faq\Model\Group;
use Maxcode\Faq\Model\Question;
use Maxcode\Faq\Model\Source\InheritYesNo;
use Maxcode\Faq\Model\Source\Status;
use PHPUnit\Framework\TestCase;

class ModelsTest extends TestCase
{
    public function testQuestionAndGroupCarryTheSharedCacheTag(): void
    {
        $helper = new ObjectManager($this);
        $question = $helper->getObject(Question::class);
        $question->setId(5);
        $group = $helper->getObject(Group::class);
        $group->setId(7);

        $this->assertSame(['maxcode_faq', 'maxcode_faq_q_5'], $question->getIdentities());
        $this->assertSame(['maxcode_faq', 'maxcode_faq_g_7'], $group->getIdentities());
    }

    public function testQuestionTagsCoverTheEntitiesItIsAssignedToNowAndBefore(): void
    {
        $question = (new ObjectManager($this))->getObject(Question::class);
        $question->setId(5);
        $question->setData('assignments', ['product' => [402], 'category' => [], 'cms_page' => [17]]);
        $question->setData('previous_assignments', ['product' => [402, 9], 'category' => [10], 'cms_page' => []]);

        $this->assertSame(
            [
                'maxcode_faq', 'maxcode_faq_q_5', 'maxcode_faq_product_402', 'maxcode_faq_cms_page_17',
                'maxcode_faq_product_9', 'maxcode_faq_category_10',
            ],
            $question->getIdentities()
        );
    }

    public function testStatusOptions(): void
    {
        $values = array_column((new Status())->toOptionArray(), 'value');

        $this->assertSame([Question::STATUS_ACTIVE, Question::STATUS_DRAFT], $values);
    }

    public function testWidgetInheritOptionsStartWithTheEmptyValue(): void
    {
        $values = array_column((new InheritYesNo())->toOptionArray(), 'value');

        $this->assertSame(['', '1', '0'], $values);
    }
}
