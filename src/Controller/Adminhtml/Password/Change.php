<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Controller\Adminhtml\Password;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\UserLockedException;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;
use YellowTeak\ChangeCustomerPassword\Model\ChangeCustomerPassword;
use YellowTeak\ChangeCustomerPassword\Model\Config;

class Change extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'YellowTeak_ChangeCustomerPassword::change_password';

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly ChangeCustomerPassword $changeCustomerPassword,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $customerId = (int) $this->getStringParam('customer_id');
        $newPassword = $this->getStringParam('new_password');
        $adminPassword = $this->getStringParam('admin_password');

        if ($customerId <= 0) {
            return $this->error(__('The customer could not be determined. Reload the page and try again.'));
        }
        if ($newPassword === '') {
            return $this->error(__('Please enter a new password.'));
        }
        if ($newPassword !== $this->getStringParam('password_confirmation')) {
            return $this->error(__('New password and confirmation do not match.'));
        }
        if ($adminPassword === '') {
            return $this->error(__('Please enter your admin password to confirm the password change.'));
        }

        $adminUser = $this->_auth->getUser();
        if (!$adminUser) {
            return $this->error(__('Unable to verify admin identity. Please log in again.'));
        }

        try {
            // Unlike verifyIdentity(), this counts failed attempts and locks the admin account.
            $adminUser->performIdentityCheck($adminPassword);
        } catch (UserLockedException $e) {
            $this->_auth->logout();

            return $this->error($e->getMessage(), true);
        } catch (AuthenticationException $e) {
            return $this->error(__('Your password is incorrect. Customer password was not changed.'));
        }

        try {
            $this->changeCustomerPassword->execute($customerId, $newPassword);
        } catch (NoSuchEntityException $e) {
            return $this->error(__('This customer no longer exists.'));
        } catch (LocalizedException $e) {
            return $this->error($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('ChangeCustomerPassword: Unexpected error changing customer password.', [
                'customer_id' => $customerId,
                'exception'   => $e,
            ]);

            return $this->error(__('An error occurred while changing the customer password.'));
        }

        $this->logger->info('ChangeCustomerPassword: Admin changed customer password.', [
            'customer_id'    => $customerId,
            'admin_username' => $adminUser->getUserName(),
        ]);

        return $this->json([
            'success' => true,
            'message' => (string) __('Customer password has been changed successfully.'),
        ]);
    }

    protected function _isAllowed(): bool
    {
        return $this->config->isEnabled() && parent::_isAllowed();
    }

    /**
     * Read a POST value, treating anything that is not a string (for example an array) as empty.
     */
    private function getStringParam(string $name): string
    {
        $value = $this->getRequest()->getPostValue($name);

        return is_string($value) ? $value : '';
    }

    private function error(Phrase|string $message, bool $reload = false): Json
    {
        return $this->json([
            'success' => false,
            'message' => (string) $message,
            'reload'  => $reload,
        ]);
    }

    private function json(array $data): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        return $result->setData($data);
    }
}
