/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

define(
    [
        'ko',
        'jquery',
        'mage/translate',
        'uiElement',
        'Magento_Ui/js/modal/modal'
    ],
    function (ko, $, $t, Component, Modal) {
        'use strict';

        /**
         * @type {object} Modal widget.
         */
        let modal;

        /**
         * @type {object} JQuery element object of the modal content.
         */
        let modalContentEl;

        return Component.extend({
            defaults: {
                template: 'Resursbank_Ordermanagement/sales/order/view/info/payment-history'
            },

            subtitle: 'This log shows what happened after the customer was ' +
                'redirected to Resurs Bank\'s payment gateway.',

            /**
             * Opens the modal.
             */
            openModal: function () {
                if (modalContentEl.length === 1) {
                    modalContentEl.modal('openModal');
                }
            },

            /**
             * Initializes the JQuery Modal widget. Can only be executed once.
             */
            initModal: function () {
                if (typeof modalContentEl === 'undefined') {
                    modalContentEl = $(
                        '#resursbank-payment-history-modal-content'
                    );

                    if (modalContentEl.length === 1) {
                        modal = Modal({
                            autoOpen: false,
                            type: 'popup',
                            responsive: true,
                            innerScroll: true,
                            title: $.mage.__(
                                'Resurs Bank - Order Payment History'
                            ),
                        modalClass: 'custom-modal',
                        buttons: []
                        }, modalContentEl);
                    }
                }
            }
        });
    }
);
