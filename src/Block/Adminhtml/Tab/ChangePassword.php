<?php
/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace YellowTeak\ChangeCustomerPassword\Block\Adminhtml\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use YellowTeak\ChangeCustomerPassword\Controller\Adminhtml\Password\Change;
use YellowTeak\ChangeCustomerPassword\Model\Config;

class ChangePassword extends Template implements TabInterface
{
    protected $_template = 'YellowTeak_ChangeCustomerPassword::tab/change_password.phtml';

    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getTabLabel(): string
    {
        return (string) __('Change Password');
    }

    public function getTabTitle(): string
    {
        return (string) __('Change Password');
    }

    public function getTabClass(): string
    {
        return '';
    }

    public function getTabUrl(): string
    {
        return '';
    }

    public function isAjaxLoaded(): bool
    {
        return false;
    }

    /**
     * Only for existing customers, and only for admins that are allowed to change passwords.
     */
    public function canShowTab(): bool
    {
        return $this->config->isEnabled()
            && $this->getCustomerId() > 0
            && $this->_authorization->isAllowed(Change::ADMIN_RESOURCE);
    }

    public function isHidden(): bool
    {
        return false;
    }

    /**
     * Customer being edited, 0 on the "new customer" page.
     */
    public function getCustomerId(): int
    {
        return (int) $this->getRequest()->getParam('id');
    }

    public function getChangePasswordUrl(): string
    {
        return $this->getUrl('yellowteak_changecustomerpassword/password/change');
    }

    public function getMinimumPasswordLength(): int
    {
        return $this->config->getMinimumPasswordLength();
    }
}
