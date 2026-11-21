<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Test\Unit\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\Action\Redirect;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Maxcode\Faq\Controller\Router;
use Maxcode\Faq\Model\Config\Settings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private ActionFactory&MockObject $actionFactory;
    private Settings&MockObject $settings;
    private Router $router;

    private HttpResponse&MockObject $response;

    protected function setUp(): void
    {
        $this->actionFactory = $this->createMock(ActionFactory::class);
        $this->settings = $this->createMock(Settings::class);
        $this->response = $this->createMock(HttpResponse::class);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(2);
        $store->method('getBaseUrl')->willReturn('https://shop.example/fr/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->router = new Router($this->actionFactory, $this->settings, $storeManager, $this->response);
    }

    private function request(string $path): Http&MockObject
    {
        $request = $this->createMock(Http::class);
        $request->method('getPathInfo')->willReturn($path);

        return $request;
    }

    public function testForwardsTheConfiguredAddress(): void
    {
        $this->settings->method('getUrlKey')->with(2)->willReturn('aide/faq');
        $this->settings->method('isEnabled')->with(2)->willReturn(true);
        $request = $this->request('/aide/faq');
        $request->expects($this->once())->method('setModuleName')->with('maxcode_faq')->willReturnSelf();
        $request->expects($this->once())->method('setControllerName')->with('index')->willReturnSelf();
        $request->expects($this->once())->method('setActionName')->with('index')->willReturnSelf();
        $forward = $this->createMock(Forward::class);
        $this->actionFactory->expects($this->once())->method('create')->with(Forward::class)->willReturn($forward);
        $this->response->expects($this->never())->method('setRedirect');

        $this->assertSame($forward, $this->router->match($request));
    }

    public function testRedirectsTheTrailingSlashAndOtherCaseToTheConfiguredAddress(): void
    {
        $this->settings->method('getUrlKey')->with(2)->willReturn('aide/faq');
        $this->settings->method('isEnabled')->with(2)->willReturn(true);
        foreach (['/aide/faq/', '/Aide/FAQ'] as $path) {
            $request = $this->request($path);
            $request->expects($this->once())->method('setDispatched')->with(true);
            $redirect = $this->createMock(Redirect::class);
            $this->actionFactory->expects($this->once())->method('create')->with(Redirect::class)->willReturn($redirect);
            $this->response->expects($this->once())->method('setRedirect')->with('https://shop.example/fr/aide/faq', 301);

            $this->assertSame($redirect, $this->router->match($request));
            $this->setUp();
            $this->settings->method('getUrlKey')->with(2)->willReturn('aide/faq');
            $this->settings->method('isEnabled')->with(2)->willReturn(true);
        }
    }

    public function testIgnoresOtherAddresses(): void
    {
        $this->settings->method('getUrlKey')->willReturn('faq');
        $this->settings->method('isEnabled')->willReturn(true);
        $this->actionFactory->expects($this->never())->method('create');

        $this->assertNull($this->router->match($this->request('/faq/autre')));
        $this->assertNull($this->router->match($this->request('/')));
    }

    public function testNoPageWhenTheAddressIsEmptyOrTheFaqDisabled(): void
    {
        $this->settings->method('getUrlKey')->willReturnOnConsecutiveCalls('', 'faq');
        $this->settings->method('isEnabled')->willReturn(false);
        $this->actionFactory->expects($this->never())->method('create');

        $this->assertNull($this->router->match($this->request('/faq')));
        $this->assertNull($this->router->match($this->request('/faq')));
    }
}
