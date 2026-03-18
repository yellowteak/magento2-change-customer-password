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

    public function canShowTab(): bool
    {
        return $this->config->isEnabled();
    }

    public function isHidden(): bool
    {
        return false;
    }
}
