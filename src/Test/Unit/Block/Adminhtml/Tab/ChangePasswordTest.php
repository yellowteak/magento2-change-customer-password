<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Test\Unit\Block\Adminhtml\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use YellowTeak\ChangeCustomerPassword\Block\Adminhtml\Tab\ChangePassword;
use YellowTeak\ChangeCustomerPassword\Model\Config;

class ChangePasswordTest extends TestCase
{
    protected function setUp(): void
    {
        // The backend template block falls back to the object manager for two helpers.
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(fn (string $type) => $this->createMock($type));
        ObjectManager::setInstance($objectManager);
    }

    /**
     * @dataProvider visibilityProvider
     */
    public function testTabVisibility(bool $enabled, mixed $customerId, bool $permitted, bool $expected): void
    {
        self::assertSame($expected, $this->createBlock($enabled, $customerId, $permitted)->canShowTab());
    }

    public static function visibilityProvider(): array
    {
        return [
            'existing customer, enabled and permitted' => [true, '5', true, true],
            'module disabled'                          => [false, '5', true, false],
            'new customer'                             => [true, null, true, false],
            'admin lacks the permission'               => [true, '5', false, false],
        ];
    }

    public function testReadsTheCustomerIdFromTheRequest(): void
    {
        self::assertSame(5, $this->createBlock(true, '5', true)->getCustomerId());
        self::assertSame(0, $this->createBlock(true, null, true)->getCustomerId());
    }

    private function createBlock(bool $enabled, mixed $customerId, bool $permitted): ChangePassword
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('id')->willReturn($customerId);

        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')
            ->with('YellowTeak_ChangeCustomerPassword::change_password')
            ->willReturn($permitted);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getAuthorization')->willReturn($authorization);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn($enabled);

        return new ChangePassword($context, new Config($scopeConfig));
    }
}
