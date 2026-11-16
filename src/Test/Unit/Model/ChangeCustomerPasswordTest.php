<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Test\Unit\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\SessionCleanerInterface;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\AddressRegistry;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Data\CustomerSecure;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use YellowTeak\ChangeCustomerPassword\Model\ChangeCustomerPassword;
use YellowTeak\ChangeCustomerPassword\Model\PasswordPolicy;

class ChangeCustomerPasswordTest extends TestCase
{
    private const CUSTOMER_ID = 5;
    private const ADDRESS_ID = 7;

    private CustomerRepositoryInterface&MockObject $customerRepository;
    private CustomerRegistry&MockObject $customerRegistry;
    private PasswordPolicy&MockObject $passwordPolicy;
    private SessionCleanerInterface&MockObject $sessionCleaner;
    private CustomerInterface&MockObject $customer;
    private Address $addressModel;
    private ChangeCustomerPassword $service;

    protected function setUp(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getId')->willReturn(self::ADDRESS_ID);

        $this->customer = $this->createMock(CustomerInterface::class);
        $this->customer->method('getEmail')->willReturn('customer@example.com');
        $this->customer->method('getAddresses')->willReturn([$address]);

        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->customerRepository->method('getById')->with(self::CUSTOMER_ID)->willReturn($this->customer);

        // Real model without its constructor, so the magic setter used by core keeps working.
        $this->addressModel = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $addressRegistry = $this->createMock(AddressRegistry::class);
        $addressRegistry->method('retrieve')->with(self::ADDRESS_ID)->willReturn($this->addressModel);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('getHash')->with('Abcdef12', true)->willReturn('new-hash');

        $this->customerRegistry = $this->createMock(CustomerRegistry::class);
        $this->passwordPolicy = $this->createMock(PasswordPolicy::class);
        $this->sessionCleaner = $this->createMock(SessionCleanerInterface::class);

        $this->service = new ChangeCustomerPassword(
            $this->customerRepository,
            $addressRegistry,
            $this->customerRegistry,
            $this->passwordPolicy,
            $encryptor,
            $this->sessionCleaner
        );
    }

    public function testSavesTheNewHashAndEndsCustomerSessions(): void
    {
        $this->customerRegistry->method('retrieveSecureData')->willReturn(new CustomerSecure());
        $this->passwordPolicy->expects(self::once())->method('validate')->with('Abcdef12', 'customer@example.com');
        $this->customerRepository->expects(self::once())->method('save')->with($this->customer, 'new-hash');
        $this->sessionCleaner->expects(self::once())->method('clearFor')->with(self::CUSTOMER_ID);

        $this->service->execute(self::CUSTOMER_ID, 'Abcdef12');

        self::assertTrue($this->addressModel->getShouldIgnoreValidation());
    }

    public function testUnlocksALockedCustomerBeforeSaving(): void
    {
        $customerSecure = new CustomerSecure([
            'failures_num'  => 6,
            'first_failure' => '2026-11-16 09:00:00',
            'lock_expires'  => '2026-11-16 10:00:00',
        ]);
        $this->customerRegistry->method('retrieveSecureData')->with(self::CUSTOMER_ID)->willReturn($customerSecure);

        // The repository reads the lock fields from the registry while saving, so they must be reset by then.
        $this->customerRepository->expects(self::once())->method('save')->willReturnCallback(
            function () use ($customerSecure): CustomerInterface {
                self::assertSame(0, $customerSecure->getFailuresNum());
                self::assertNull($customerSecure->getFirstFailure());
                self::assertNull($customerSecure->getLockExpires());

                return $this->customer;
            }
        );

        $this->service->execute(self::CUSTOMER_ID, 'Abcdef12');
    }

    public function testChangesNothingWhenThePasswordViolatesThePolicy(): void
    {
        $this->passwordPolicy->method('validate')->willThrowException(new InputException(__('Too weak.')));
        $this->customerRegistry->expects(self::never())->method('retrieveSecureData');
        $this->customerRepository->expects(self::never())->method('save');
        $this->sessionCleaner->expects(self::never())->method('clearFor');

        $this->expectException(InputException::class);

        $this->service->execute(self::CUSTOMER_ID, 'Abcdef12');
    }

    public function testKeepsCustomerSessionsWhenSavingFails(): void
    {
        $this->customerRegistry->method('retrieveSecureData')->willReturn(new CustomerSecure());
        $this->customerRepository->method('save')->willThrowException(new \RuntimeException('Database is gone.'));
        $this->sessionCleaner->expects(self::never())->method('clearFor');

        $this->expectException(\RuntimeException::class);

        $this->service->execute(self::CUSTOMER_ID, 'Abcdef12');
    }
}
