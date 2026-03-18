<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Plugin\Controller;

use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\Adminhtml\Index\Save;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use YellowTeak\ChangeCustomerPassword\Model\Config;
use Psr\Log\LoggerInterface;

class SavePlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly AdminSession $adminSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerRegistry $customerRegistry,
        private readonly EncryptorInterface $encryptor,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly LoggerInterface $logger
    ) {}

    public function aroundExecute(Save $subject, callable $proceed)
    {
        if (!$this->config->isEnabled()) {
            return $proceed();
        }

        $request = $subject->getRequest();
        $changePasswordData = $request->getPost('change_password') ?? [];
        $newPassword = $changePasswordData['new_password'] ?? '';
        $passwordConfirmation = $changePasswordData['password_confirmation'] ?? '';
        $adminPassword = $changePasswordData['admin_password'] ?? '';

        if (empty($newPassword) && empty($passwordConfirmation)) {
            return $proceed();
        }

        $customerData = $request->getPost('customer') ?? [];
        $customerId = isset($customerData['entity_id']) ? (int)$customerData['entity_id'] : 0;

        if (!$customerId) {
            return $proceed();
        }

        // Validate minimum password length using AccountManagement::getMinPasswordLength()
        $minPasswordLength = $this->config->getMinimumPasswordLength();
        if (mb_strlen($newPassword) < $minPasswordLength) {
            $this->messageManager->addErrorMessage(
                __('New password must be at least %1 characters long.', $minPasswordLength)
            );
            return $this->redirectToEdit($customerId);
        }

        // Validate passwords match
        if ($newPassword !== $passwordConfirmation) {
            $this->messageManager->addErrorMessage(__('New password and confirmation do not match.'));
            return $this->redirectToEdit($customerId);
        }

        // Validate admin password provided
        if (empty($adminPassword)) {
            $this->messageManager->addErrorMessage(__('Please enter your admin password to confirm the password change.'));
            return $this->redirectToEdit($customerId);
        }

        // Verify admin identity
        $adminUser = $this->adminSession->getUser();
        if (!$adminUser) {
            $this->messageManager->addErrorMessage(__('Unable to verify admin identity. Please log in again.'));
            return $this->redirectToEdit($customerId);
        }
        try {
            if (!$adminUser->verifyIdentity($adminPassword)) {
                $this->messageManager->addErrorMessage(__('Your password is incorrect. Customer password was not changed.'));
                return $this->redirectToEdit($customerId);
            }
        } catch (AuthenticationException $e) {
            $this->messageManager->addErrorMessage(__('Your password is incorrect. Customer password was not changed.'));
            return $this->redirectToEdit($customerId);
        }

        // Run original save
        $result = $proceed();

        // Change customer password
        try {
            $customer = $this->customerRepository->getById($customerId);
            $customerSecure = $this->customerRegistry->retrieveSecureData($customerId);
            $customerSecure->setRpToken(null);
            $customerSecure->setRpTokenCreatedAt(null);
            $customerSecure->setPasswordHash($this->encryptor->getHash($newPassword, true));
            $this->customerRepository->save($customer);

            $this->logger->info('ChangeCustomerPassword: Admin changed customer password.', [
                'customer_id'    => $customerId,
                'customer_email' => $customer->getEmail(),
                'admin_username' => $adminUser->getUserName(),
            ]);

            $this->messageManager->addSuccessMessage(__('Customer password has been changed successfully.'));
        } catch (LocalizedException $e) {
            $this->logger->error('ChangeCustomerPassword: Failed to change customer password.', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('ChangeCustomerPassword: Unexpected error changing customer password.', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            $this->messageManager->addErrorMessage(__('An error occurred while changing the customer password.'));
        }

        return $result;
    }

    private function redirectToEdit(int $customerId)
    {
        $redirect = $this->redirectFactory->create();
        return $redirect->setPath('customer/index/edit', ['id' => $customerId]);
    }
}
