<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Test\Unit\Model;

use Magento\Customer\Model\Customer\CredentialsValidator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\TestCase;
use YellowTeak\ChangeCustomerPassword\Model\Config;
use YellowTeak\ChangeCustomerPassword\Model\PasswordPolicy;

class PasswordPolicyTest extends TestCase
{
    private const EMAIL = 'customer@example.com';

    /**
     * @dataProvider validPasswordProvider
     */
    public function testAcceptsPasswordThatMeetsThePolicy(string $password): void
    {
        $this->createPolicy()->validate($password, self::EMAIL);

        $this->addToAssertionCount(1);
    }

    public static function validPasswordProvider(): array
    {
        return [
            'upper, lower and digits'      => ['Abcdef12'],
            'lower, digits and special'    => ['abcdef1!'],
            'space inside the password'    => ['Abc def 12'],
            'exactly the maximum length'   => [str_repeat('Aa1x', 64)],
            'multibyte counts as one char' => ['Äbcdéf12'],
        ];
    }

    /**
     * @dataProvider invalidPasswordProvider
     */
    public function testRejectsPasswordThatViolatesThePolicy(string $password, string $expectedMessage): void
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->createPolicy()->validate($password, self::EMAIL);
    }

    public static function invalidPasswordProvider(): array
    {
        return [
            'too short'                 => ['Abc12', 'at least 8 characters'],
            'too short in multibyte'    => ['Äbcdé12', 'at least 8 characters'],
            'longer than the maximum'   => [str_repeat('Aa1x', 64) . 'x', 'at most 256 characters'],
            'leading space'             => [' Abcdef12', "can't begin or end with a space"],
            'trailing space'            => ['Abcdef12 ', "can't begin or end with a space"],
            'too few character classes' => ['abcdefgh1', 'classes of characters in password is 3'],
            'same as the email address' => ['Customer@Example.com', "can't be the same as the email address"],
        ];
    }

    public function testUsesTheConfiguredLimits(): void
    {
        $this->createPolicy(4, 1)->validate('abcd', self::EMAIL);

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('classes of characters in password is 4');

        $this->createPolicy(4, 4)->validate('Abcdef12', self::EMAIL);
    }

    private function createPolicy(int $minimumLength = 8, int $requiredClasses = 3): PasswordPolicy
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): string => match ($path) {
                'customer/password/minimum_password_length'           => (string) $minimumLength,
                'customer/password/required_character_classes_number' => (string) $requiredClasses,
            }
        );

        return new PasswordPolicy(new Config($scopeConfig), new CredentialsValidator());
    }
}
