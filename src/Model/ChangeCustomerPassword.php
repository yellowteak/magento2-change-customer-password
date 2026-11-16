<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\SessionCleanerInterface;
use Magento\Customer\Model\AddressRegistry;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class ChangeCustomerPassword
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRegistry $addressRegistry,
        private readonly CustomerRegistry $customerRegistry,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly EncryptorInterface $encryptor,
        private readonly SessionCleanerInterface $sessionCleaner
    ) {
    }

    /**
     * Set a new password for the customer, lift a login lock and end all of the customer's sessions.
     *
     * @throws NoSuchEntityException
     * @throws InputException
     * @throws LocalizedException
     */
    public function execute(int $customerId, string $newPassword): void
    {
        $customer = $this->customerRepository->getById($customerId);
        $this->passwordPolicy->validate($newPassword, (string) $customer->getEmail());

        // Same as core: an invalid legacy address must not block a password change.
        foreach ($customer->getAddresses() ?? [] as $address) {
            $this->addressRegistry->retrieve($address->getId())->setShouldIgnoreValidation(true);
        }

        // Lift a lock caused by failed logins, like core does on a password reset. The repository
        // copies these values from the registry, so this is persisted in the same save as the password.
        $customerSecure = $this->customerRegistry->retrieveSecureData($customerId);
        $customerSecure->setFailuresNum(0);
        $customerSecure->setFirstFailure(null);
        $customerSecure->setLockExpires(null);

        // Passing the hash makes the repository reset the password reset token as well.
        $this->customerRepository->save($customer, $this->encryptor->getHash($newPassword, true));
        $this->sessionCleaner->clearFor($customerId);
    }
}
