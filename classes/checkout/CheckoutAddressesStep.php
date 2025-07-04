<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
use Symfony\Contracts\Translation\TranslatorInterface;
use tools\profiling\Tools;

class CheckoutAddressesStepCore extends AbstractCheckoutStep
{
    protected $template = 'checkout/_partials/steps/addresses.tpl';

    private $addressForm;
    private $use_same_address = true;
    private $show_delivery_address_form = false;
    private $show_invoice_address_form = false;
    private $form_has_continue_button = false;

    /**
     * @param Context $context
     * @param TranslatorInterface $translator
     * @param CustomerAddressForm $addressForm
     */
    public function __construct(
        Context $context,
        TranslatorInterface $translator,
        CustomerAddressForm $addressForm
    ) {
        parent::__construct($context, $translator);
        $this->addressForm = $addressForm;
    }

    public function getDataToPersist()
    {
        return [
            'use_same_address' => $this->use_same_address,
        ];
    }

    public function restorePersistedData(array $data)
    {
        if (array_key_exists('use_same_address', $data)) {
            $this->use_same_address = $data['use_same_address'];
        }

        return $this;
    }

    public function handleRequest(array $requestParams = [])
    {
        $this->addressForm->setAction($this->getCheckoutSession()->getCheckoutURL());

        if (array_key_exists('use_same_address', $requestParams)) {
            $this->use_same_address = (bool) $requestParams['use_same_address'];
            if (!$this->use_same_address) {
                $this->setCurrent(true);
            }
        }

        if (isset($requestParams['cancelAddress'])) {
            if ($requestParams['cancelAddress'] === 'invoice') {
                if ($this->getCheckoutSession()->getCustomerAddressesCount() < 2) {
                    $this->use_same_address = true;
                }
            }
            $this->setCurrent(true);
        }

        // Can't really hurt to set the firstname and lastname.
        $this->addressForm->fillWith([
            'firstname' => $this->getCheckoutSession()->getCustomer()->firstname,
            'lastname' => $this->getCheckoutSession()->getCustomer()->lastname,
        ]);

        if (isset($requestParams['saveAddress'])) {
            $saved = $this->addressForm->fillWith($requestParams)->submit();
            if (!$saved) {
                $this->setCurrent(true);
                $this->getCheckoutProcess()->setHasErrors(true);
                if ($requestParams['saveAddress'] === 'delivery') {
                    $this->show_delivery_address_form = true;
                } else {
                    $this->show_invoice_address_form = true;
                }
            } else {
                if ($requestParams['saveAddress'] === 'delivery') {
                    $this->use_same_address = isset($requestParams['use_same_address']);
                }
                $id_address = $this->addressForm->getAddress()->id;
                if ($requestParams['saveAddress'] === 'delivery') {
                    $this->getCheckoutSession()->setIdAddressDelivery($id_address);
                    $idAddressInvoice = $this->use_same_address ? $id_address : null;
                    $this->getCheckoutSession()->setIdAddressInvoice($idAddressInvoice);
                } else {
                    $this->getCheckoutSession()->setIdAddressInvoice($id_address);
                }
            }
        } elseif (isset($requestParams['newAddress'])) {
            // while a form is open, do not go to next step
            $this->setCurrent(true);
            if ($requestParams['newAddress'] === 'delivery') {
                $this->show_delivery_address_form = true;
            } else {
                $this->show_invoice_address_form = true;
            }
            $this->addressForm->fillWith($requestParams);
            $this->form_has_continue_button = $this->use_same_address;
        } elseif (isset($requestParams['editAddress'])) {
            // while a form is open, do not go to next step
            $this->setCurrent(true);
            if ($requestParams['editAddress'] === 'delivery') {
                $this->show_delivery_address_form = true;
            } else {
                $this->show_invoice_address_form = true;
            }
            $this->addressForm->loadAddressById($requestParams['id_address']);
        } elseif (isset($requestParams['deleteAddress'])) {
            $addressPersister = new CustomerAddressPersister(
                $this->context->customer,
                $this->context->cart,
                Tools::getToken(true, $this->context)
            );

            $deletionResult = (bool) $addressPersister->delete(
                new Address((int) Tools::getValue('id_address'), $this->context->language->id),
                Tools::getValue('token')
            );
            if ($deletionResult) {
                $this->context->controller->success[] = $this->getTranslator()->trans(
                    'Address successfully deleted.',
                    [],
                    'Shop.Notifications.Success'
                );
                $this->context->controller->redirectWithNotifications(
                    $this->getCheckoutSession()->getCheckoutURL()
                );
            } else {
                $this->getCheckoutProcess()->setHasErrors(true);
                $this->context->controller->errors[] = $this->getTranslator()->trans(
                    'Could not delete address.',
                    [],
                    'Shop.Notifications.Error'
                );
            }
        }

        if (isset($requestParams['confirm-addresses'])) {
            if (isset($requestParams['id_address_delivery'])) {
                $id_address = $requestParams['id_address_delivery'];

                if (!Customer::customerHasAddress($this->getCheckoutSession()->getCustomer()->id, $id_address)) {
                    $this->getCheckoutProcess()->setHasErrors(true);
                } else {
                    if ($this->getCheckoutSession()->getIdAddressDelivery() != $id_address) {
                        $this->setCurrent(true);
                        $this->getCheckoutProcess()->invalidateAllStepsAfterCurrent();
                    }

                    $this->getCheckoutSession()->setIdAddressDelivery($id_address);
                    if ($this->use_same_address) {
                        $this->getCheckoutSession()->setIdAddressInvoice($id_address);
                    }
                }
            }

            if (isset($requestParams['id_address_invoice'])) {
                $id_address = $requestParams['id_address_invoice'];
                if (!Customer::customerHasAddress($this->getCheckoutSession()->getCustomer()->id, $id_address)) {
                    $this->getCheckoutProcess()->setHasErrors(true);
                } else {
                    $this->getCheckoutSession()->setIdAddressInvoice($id_address);
                }
            }

            if (!$this->getCheckoutProcess()->hasErrors()) {
                $this->setNextStepAsCurrent();
                $this->setComplete(
                    $this->getCheckoutSession()->getIdAddressInvoice()
                    && $this->getCheckoutSession()->getIdAddressDelivery()
                );

                // if we just pushed the invoice address form, we are using another address for invoice
                // (param 'id_address_delivery' is only pushed in invoice address form)
                if (isset($requestParams['saveAddress'], $requestParams['id_address_delivery'])) {
                    $this->use_same_address = false;
                }
            }
        }

        $addresses_count = $this->getCheckoutSession()->getCustomerAddressesCount();

        if ($addresses_count === 0) {
            $this->show_delivery_address_form = true;
        } elseif ($addresses_count < 2 && !$this->use_same_address) {
            $this->show_invoice_address_form = true;
            $this->setComplete(false);
        }

        if ($this->show_invoice_address_form) {
            // show continue button because form is at the end of the step
            $this->form_has_continue_button = true;
        } elseif ($this->show_delivery_address_form) {
            // only show continue button if we're sure
            // our form is at the bottom of the step
            if ($this->use_same_address || $addresses_count < 2) {
                $this->form_has_continue_button = true;
            }
        }

        $this->setTitle($this->getTranslator()->trans('Addresses', [], 'Shop.Theme.Checkout'));

        return $this;
    }

    public function getTemplateParameters()
    {
        $idAddressDelivery = (int) $this->getCheckoutSession()->getIdAddressDelivery();
        $idAddressInvoice = (int) $this->getCheckoutSession()->getIdAddressInvoice();
        $params = [
            'address_form' => $this->addressForm->getProxy(),
            'use_same_address' => $this->use_same_address,
            'use_different_address_url' => $this->context->link->getPageLink(
                'order',
                null,
                null,
                ['use_same_address' => 0]
            ),
            'new_address_delivery_url' => $this->context->link->getPageLink(
                'order',
                null,
                null,
                ['newAddress' => 'delivery']
            ),
            'new_address_invoice_url' => $this->context->link->getPageLink(
                'order',
                null,
                null,
                ['newAddress' => 'invoice']
            ),
            'id_address' => (int) Tools::getValue('id_address'),
            'id_address_delivery' => $idAddressDelivery,
            'id_address_invoice' => $idAddressInvoice,
            'show_delivery_address_form' => $this->show_delivery_address_form,
            'show_invoice_address_form' => $this->show_invoice_address_form,
            'form_has_continue_button' => $this->form_has_continue_button,
        ];

        /** @var OrderControllerCore $controller */
        $controller = $this->context->controller;
        if ($controller instanceof OrderController) {
            $warnings = $controller->checkoutWarning;
            $addressWarning = $warnings['address'] ?? false;
            $invalidAddresses = $warnings['invalid_addresses'] ?? [];

            $errors = [];
            if (in_array($idAddressDelivery, $invalidAddresses)) {
                $errors['delivery_address_error'] = $addressWarning;
            }

            if (in_array($idAddressInvoice, $invalidAddresses)) {
                $errors['invoice_address_error'] = $addressWarning;
            }

            if ($this->show_invoice_address_form
                || $idAddressInvoice != $idAddressDelivery
                || !empty($errors['invoice_address_error'])
            ) {
                $this->use_same_address = false;
            }

            // Add specific parameters
            $params = array_replace(
                $params,
                [
                    'not_valid_addresses' => implode(',', $invalidAddresses),
                    'use_same_address' => $this->use_same_address,
                ],
                $errors
            );
        }

        return $params;
    }

    public function render(array $extraParams = [])
    {
        return $this->renderTemplate(
            $this->getTemplate(),
            $extraParams,
            $this->getTemplateParameters()
        );
    }
}
