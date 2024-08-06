# Resurs Bank - Magento 2 module - Order Management - Backport

## Description

Functionality to interact with payments at Resurs Bank, backported for older Magento.

---

## Prerequisites

* [Magento 2](https://devdocs.magento.com/guides/v2.4/install-gde/bk-install-guide.html) [Supports Magento 2.3.x+]
* PHP 7.4 minimum

---

#### 1.6.1

* Fixed problem on shipment view.

#### 1.7.4

* Added feature to automatically generate invoice for payment methods that automatically debit purchases.
* Expanded documentation.
* Replaced logotypes.
* Added support for PHP 8.1

#### 1.7.5

* Order confirmation email is now sent immediately after order is placed using a payment method that does not come from Resurs Bank.

#### 1.7.7

* Fixed null pointer.
* Order status and state are now more accurately reflected in history entries.

#### 1.7.8

* 2.4.5 compatibility update.

#### 1.7.9

* Updated discount and VAT handling.

#### 1.8.0

* Added two minute delay for all callback execution to mitigate race conditions invoked by other third party modules.

#### 1.8.3

* Added saftey checks, ensuring code doesn't execute when not applicable to the payment method applied on an order.

#### 1.8.7

* Fixed order frontend visibility issue.
