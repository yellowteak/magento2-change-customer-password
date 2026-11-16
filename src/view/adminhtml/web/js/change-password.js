/*
 * Copyright (c) 2026 Yellow Teak
 * SPDX-License-Identifier: MIT
 */

define([
    'jquery',
    'underscore',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, _, alert, $t) {
    'use strict';

    /**
     * Wires the "Change Password" tab to its own controller, independent of the customer form save.
     *
     * @param {HTMLElement} element - Tab wrapper carrying data-url and data-customer-id.
     */
    return function (element) {
        var $root = $(element),
            $messages = $root.find('[data-role="messages"]'),
            $button = $root.find('[data-role="change-password-button"]'),
            $fields = $root.find('input[data-field]');

        /**
         * @param {String} text
         * @param {Boolean} isError
         */
        function showMessage(text, isError) {
            var type = isError ? 'error' : 'success';

            $messages.empty().append(
                $('<div></div>', {
                    'class': 'message message-' + type + ' ' + type
                }).append($('<div></div>').text(text))
            );
        }

        /**
         * @returns {Object}
         */
        function collectData() {
            var data = {
                'customer_id': $root.data('customerId')
            };

            $fields.each(function () {
                data[$(this).data('field')] = $(this).val();
            });

            return data;
        }

        /**
         * @param {Object} response
         */
        function onResponse(response) {
            if (!response || response.ajaxExpired) {
                return;
            }

            if (response.reload) {
                alert({
                    content: _.escape(response.message),
                    actions: {
                        /** Reload so the admin lands on the login page. */
                        always: function () {
                            window.location.reload();
                        }
                    }
                });

                return;
            }

            showMessage(response.message, !response.success);

            if (response.success) {
                $fields.val('');
            } else {
                $fields.filter('[data-field="admin_password"]').val('');
            }
        }

        /**
         * Post the fields to the change password controller.
         */
        function submit() {
            if ($button.prop('disabled')) {
                return;
            }

            $button.prop('disabled', true);
            $messages.empty();

            $.ajax({
                url: $root.data('url'),
                type: 'POST',
                dataType: 'json',
                data: collectData(),
                showLoader: true
            }).done(onResponse).fail(function () {
                showMessage($t('The password could not be changed. Reload the page and try again.'), true);
            }).always(function () {
                $button.prop('disabled', false);
            });
        }

        $button.on('click', submit);
        $fields.on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submit();
            }
        });
    };
});
