<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Test\Unit\Controller\Adminhtml\Password;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\UserLockedException;
use Magento\User\Model\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use YellowTeak\ChangeCustomerPassword\Controller\Adminhtml\Password\Change;
use YellowTeak\ChangeCustomerPassword\Model\ChangeCustomerPassword;
use YellowTeak\ChangeCustomerPassword\Model\Config;

class ChangeTest extends TestCase
{
    private const VALID_POST = [
        'customer_id'           => '5',
        'new_password'          => 'Abcdef12',
        'password_confirmation' => 'Abcdef12',
        'admin_password'        => 'admin-secret',
    ];

    private Auth&MockObject $auth;
    private User&MockObject $adminUser;
    private AuthorizationInterface&MockObject $authorization;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private ChangeCustomerPassword&MockObject $changeCustomerPassword;
    private LoggerInterface&MockObject $logger;

    /** @var array<string, mixed> */
    private array $post = [];

    /** @var array<string, mixed> */
    private array $response = [];

    protected function setUp(): void
    {
        $this->adminUser = $this->createMock(User::class);
        $this->adminUser->method('getUserName')->willReturn('support');
        $this->auth = $this->createMock(Auth::class);
        $this->auth->method('getUser')->willReturn($this->adminUser);
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->changeCustomerPassword = $this->createMock(ChangeCustomerPassword::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testChangesThePasswordAfterASuccessfulIdentityCheck(): void
    {
        $this->adminUser->expects(self::once())->method('performIdentityCheck')->with('admin-secret');
        $this->changeCustomerPassword->expects(self::once())->method('execute')->with(5, 'Abcdef12');
        $this->auth->expects(self::never())->method('logout');
        $this->logger->expects(self::once())->method('info')->with(
            self::anything(),
            ['customer_id' => 5, 'admin_username' => 'support']
        );

        $this->execute(self::VALID_POST);

        self::assertTrue($this->response['success']);
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testRejectsInvalidInputBeforeCheckingTheAdminPassword(array $override, string $expected): void
    {
        $this->adminUser->expects(self::never())->method('performIdentityCheck');
        $this->changeCustomerPassword->expects(self::never())->method('execute');

        $this->execute(array_merge(self::VALID_POST, $override));

        self::assertFalse($this->response['success']);
        self::assertStringContainsString($expected, $this->response['message']);
    }

    public static function invalidInputProvider(): array
    {
        return [
            'no customer id'              => [['customer_id' => ''], 'customer could not be determined'],
            'customer id is not a number' => [['customer_id' => 'abc'], 'customer could not be determined'],
            'customer id as array'        => [['customer_id' => ['5']], 'customer could not be determined'],
            'empty new password'          => [['new_password' => ''], 'enter a new password'],
            'new password as array'       => [['new_password' => ['Abcdef12']], 'enter a new password'],
            'confirmation differs'        => [['password_confirmation' => 'Abcdef13'], 'do not match'],
            'confirmation as array'       => [['password_confirmation' => ['Abcdef12']], 'do not match'],
            'empty admin password'        => [['admin_password' => ''], 'enter your admin password'],
        ];
    }

    public function testDoesNotChangeThePasswordWhenTheAdminPasswordIsWrong(): void
    {
        $this->adminUser->method('performIdentityCheck')
            ->willThrowException(new AuthenticationException(__('Invalid.')));
        $this->changeCustomerPassword->expects(self::never())->method('execute');
        $this->auth->expects(self::never())->method('logout');

        $this->execute(self::VALID_POST);

        self::assertFalse($this->response['success']);
        self::assertFalse($this->response['reload']);
        self::assertStringContainsString('Your password is incorrect', $this->response['message']);
    }

    public function testLogsTheAdminOutWhenTheAccountGetsLocked(): void
    {
        $this->adminUser->method('performIdentityCheck')
            ->willThrowException(new UserLockedException(__('Your account is temporarily disabled.')));
        $this->changeCustomerPassword->expects(self::never())->method('execute');
        $this->auth->expects(self::once())->method('logout');

        $this->execute(self::VALID_POST);

        self::assertFalse($this->response['success']);
        self::assertTrue($this->response['reload']);
        self::assertSame('Your account is temporarily disabled.', $this->response['message']);
    }

    public function testReportsAPolicyViolationToTheAdmin(): void
    {
        $this->changeCustomerPassword->method('execute')
            ->willThrowException(new InputException(__('The password needs at least 8 characters.')));
        $this->logger->expects(self::never())->method('info');

        $this->execute(self::VALID_POST);

        self::assertFalse($this->response['success']);
        self::assertSame('The password needs at least 8 characters.', $this->response['message']);
    }

    public function testReportsAMissingCustomer(): void
    {
        $this->changeCustomerPassword->method('execute')
            ->willThrowException(new NoSuchEntityException(__('No such entity.')));

        $this->execute(self::VALID_POST);

        self::assertFalse($this->response['success']);
        self::assertStringContainsString('no longer exists', $this->response['message']);
    }

    public function testLogsAnUnexpectedErrorWithoutExposingIt(): void
    {
        $this->changeCustomerPassword->method('execute')
            ->willThrowException(new \RuntimeException('SQLSTATE[HY000] secret detail'));
        $this->logger->expects(self::once())->method('error');
        $this->logger->expects(self::never())->method('info');

        $this->execute(self::VALID_POST);

        self::assertFalse($this->response['success']);
        self::assertStringNotContainsString('SQLSTATE', $this->response['message']);
    }

    /**
     * @dataProvider accessProvider
     */
    public function testIsOnlyAllowedWhenEnabledAndPermitted(bool $enabled, bool $permitted, bool $expected): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn($enabled);
        $this->authorization->method('isAllowed')
            ->with('YellowTeak_ChangeCustomerPassword::change_password')
            ->willReturn($permitted);

        $controller = $this->createController();

        self::assertSame($expected, (new \ReflectionMethod($controller, '_isAllowed'))->invoke($controller));
    }

    public static function accessProvider(): array
    {
        return [
            'enabled and permitted'   => [true, true, true],
            'enabled, not permitted'  => [true, false, false],
            'disabled, permitted'     => [false, true, false],
            'disabled, not permitted' => [false, false, false],
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    private function execute(array $post): void
    {
        $this->post = $post;
        $this->createController()->execute();
    }

    private function createController(): Change
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPostValue')->willReturnCallback(fn (string $name) => $this->post[$name] ?? null);

        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(function (array $data) use ($json): Json {
            $this->response = $data;

            return $json;
        });
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($json);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getAuth')->willReturn($this->auth);
        $context->method('getAuthorization')->willReturn($this->authorization);
        $context->method('getResultFactory')->willReturn($resultFactory);

        return new Change(
            $context,
            new Config($this->scopeConfig),
            $this->changeCustomerPassword,
            $this->logger
        );
    }
}
