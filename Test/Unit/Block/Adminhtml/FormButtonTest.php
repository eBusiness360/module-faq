<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Block\Adminhtml;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Maxcode\Faq\Block\Adminhtml\FormButton;
use PHPUnit\Framework\TestCase;

class FormButtonTest extends TestCase
{
    private function button(string $type, int $id): FormButton
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('question_id')->willReturn((string) $id);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $path, array $params = []): string => 'https://admin.test/' . $path . ($params ? '?' . http_build_query($params) : '')
        );
        $escaper = new Escaper();
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($url);
        $context->method('getEscaper')->willReturn($escaper);

        return new FormButton($context, $type, 'maxcode_faq/question', 'question_id', 'maxcode_faq_question_form');
    }

    public function testDeleteButtonExistsOnlyForAnExistingQuestion(): void
    {
        $this->assertSame([], $this->button('delete', 0)->getButtonData());
        $data = $this->button('delete', 12)->getButtonData();

        $this->assertSame('delete', $data['class']);
        $this->assertStringContainsString('https://admin.test/maxcode_faq/question/delete?question_id=12', $data['on_click']);
        $this->assertStringContainsString('deleteConfirm(', $data['on_click']);
    }

    public function testBackGoesToTheGrid(): void
    {
        $this->assertStringContainsString('maxcode_faq/question/index', $this->button('back', 0)->getButtonData()['on_click']);
    }

    public function testSaveButtonsTargetTheForm(): void
    {
        $save = $this->button('save', 0)->getButtonData();
        $continue = $this->button('save_continue', 0)->getButtonData();

        $this->assertSame(
            'maxcode_faq_question_form.maxcode_faq_question_form',
            $save['data_attribute']['mage-init']['buttonAdapter']['actions'][0]['targetName']
        );
        $this->assertSame([false], $save['data_attribute']['mage-init']['buttonAdapter']['actions'][0]['params']);
        $this->assertSame(
            [true, ['back' => 'continue']],
            $continue['data_attribute']['mage-init']['buttonAdapter']['actions'][0]['params']
        );
    }
}
