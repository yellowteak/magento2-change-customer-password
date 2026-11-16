<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Model;

use Magento\Customer\Model\Customer\CredentialsValidator;
use Magento\Framework\Exception\InputException;

/**
 * Applies the storefront password rules to a password set from the admin.
 *
 * Mirrors \Magento\Customer\Model\AccountManagement::checkPasswordStrength, which is protected and
 * therefore cannot be called directly.
 */
class PasswordPolicy
{
    private const MAX_PASSWORD_LENGTH = 256;

    public function __construct(
        private readonly Config $config,
        private readonly CredentialsValidator $credentialsValidator
    ) {
    }

    /**
     * @throws InputException
     */
    public function validate(string $password, string $customerEmail): void
    {
        $length = mb_strlen($password, 'UTF-8');

        if ($length > self::MAX_PASSWORD_LENGTH) {
            throw new InputException(
                __('Please enter a password with at most %1 characters.', self::MAX_PASSWORD_LENGTH)
            );
        }

        $minimumLength = $this->config->getMinimumPasswordLength();
        if ($length < $minimumLength) {
            throw new InputException(
                __('The password needs at least %1 characters. Create a new password and try again.', $minimumLength)
            );
        }

        if (mb_strlen(trim($password), 'UTF-8') !== $length) {
            throw new InputException(
                __("The password can't begin or end with a space. Verify the password and try again.")
            );
        }

        $requiredClasses = $this->config->getRequiredCharacterClassesNumber();
        if ($this->countCharacterClasses($password) < $requiredClasses) {
            throw new InputException(
                __(
                    'Minimum of different classes of characters in password is %1.'
                    . ' Classes of characters: Lower Case, Upper Case, Digits, Special Characters.',
                    $requiredClasses
                )
            );
        }

        $this->credentialsValidator->checkPasswordDifferentFromEmail($customerEmail, $password);
    }

    private function countCharacterClasses(string $password): int
    {
        $count = 0;
        foreach (['/[0-9]+/', '/[A-Z]+/', '/[a-z]+/', '/[^a-zA-Z0-9]+/'] as $pattern) {
            if (preg_match($pattern, $password)) {
                $count++;
            }
        }

        return $count;
    }
}
