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

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use tools\profiling\Hook;
use tools\profiling\Tools;

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
class AdminPdfControllerCore extends AdminController
{
    public function postProcess()
    {
        parent::postProcess();

        // We want to be sure that displaying PDF is the last thing this controller will do
        exit;
    }

    public function initProcess()
    {
        parent::initProcess();
        $this->checkCacheFolder();
        $access = Profile::getProfileAccess($this->context->employee->id_profile, (int) Tab::getIdFromClassName('AdminOrders'));
        if ($access['view'] === '1' && ($action = Tools::getValue('submitAction'))) {
            $this->action = $action;
        } else {
            $this->errors[] = $this->trans('You do not have permission to view this.', [], 'Admin.Notifications.Error');
        }
    }

    public function checkCacheFolder()
    {
        if (!is_dir(_PS_CACHE_DIR_ . 'tcpdf/')) {
            mkdir(_PS_CACHE_DIR_ . 'tcpdf/');
        }
    }

    public function processGenerateInvoicePdf()
    {
        if (Tools::isSubmit('id_order')) {
            $sfContainer = SymfonyContainer::getInstance();
            $sfRouter = $sfContainer->get('router');
            Tools::redirectAdmin($sfRouter->generate(
                'admin_orders_generate_invoice_pdf',
                ['orderId' => (int) Tools::getValue('id_order')]
            ));
        } elseif (Tools::isSubmit('id_order_invoice')) {
            $this->generateInvoicePDFByIdOrderInvoice(Tools::getValue('id_order_invoice'));
        } else {
            die($this->trans('The order ID -- or the invoice order ID -- is missing.', [], 'Admin.Orderscustomers.Notification'));
        }
    }

    public function processGenerateOrderSlipPDF()
    {
        $order_slip = new OrderSlip((int) Tools::getValue('id_order_slip'));
        $order = new Order((int) $order_slip->id_order);

        if (!Validate::isLoadedObject($order)) {
            die($this->trans('The order cannot be found within your database.', [], 'Admin.Orderscustomers.Notification'));
        }

        $this->generatePDF($order_slip, PDF::TEMPLATE_ORDER_SLIP);
    }

    public function processGenerateDeliverySlipPDF()
    {
        $sfContainer = SymfonyContainer::getInstance();
        $sfRouter = $sfContainer->get('router');
        if (Tools::isSubmit('id_order')) {
            Tools::redirectAdmin($sfRouter->generate(
                'admin_orders_generate_delivery_slip_pdf',
                ['orderId' => (int) Tools::getValue('id_order')]
            ));
        } elseif (Tools::isSubmit('id_order_invoice')) {
            $this->generateDeliverySlipPDFByIdOrderInvoice((int) Tools::getValue('id_order_invoice'));
        } elseif (Tools::isSubmit('id_delivery')) {
            $order = Order::getByDelivery((int) Tools::getValue('id_delivery'));
            Tools::redirectAdmin($sfRouter->generate(
                'admin_orders_generate_delivery_slip_pdf',
                ['orderId' => (int) $order->id]
            ));
        } else {
            die($this->trans('The order ID -- or the invoice order ID -- is missing.', [], 'Admin.Orderscustomers.Notification'));
        }
    }

    public function processGenerateInvoicesPDF()
    {
        $order_invoice_collection = OrderInvoice::getByDateInterval(Tools::getValue('date_from'), Tools::getValue('date_to'));

        if (!count($order_invoice_collection)) {
            die($this->trans('No invoice was found.', [], 'Admin.Orderscustomers.Notification'));
        }

        $this->generatePDF($order_invoice_collection, PDF::TEMPLATE_INVOICE);
    }

    public function processGenerateInvoicesPDF2()
    {
        $order_invoice_collection = [];
        foreach (explode('-', Tools::getValue('id_order_state')) as $id_order_state) {
            if (is_array($order_invoices = OrderInvoice::getByStatus((int) $id_order_state))) {
                $order_invoice_collection = array_merge($order_invoices, $order_invoice_collection);
            }
        }

        if (!count($order_invoice_collection)) {
            die($this->trans('No invoice was found.', [], 'Admin.Orderscustomers.Notification'));
        }

        $this->generatePDF($order_invoice_collection, PDF::TEMPLATE_INVOICE);
    }

    public function processGenerateOrderSlipsPDF()
    {
        $id_order_slips_list = OrderSlip::getSlipsIdByDate(Tools::getValue('date_from'), Tools::getValue('date_to'));
        if (!count($id_order_slips_list)) {
            die($this->trans('No order slips were found.', [], 'Admin.Orderscustomers.Notification'));
        }

        $order_slips = [];
        foreach ($id_order_slips_list as $id_order_slips) {
            $order_slips[] = new OrderSlip((int) $id_order_slips);
        }

        $this->generatePDF($order_slips, PDF::TEMPLATE_ORDER_SLIP);
    }

    public function processGenerateDeliverySlipsPDF()
    {
        $order_invoice_collection = OrderInvoice::getByDeliveryDateInterval(Tools::getValue('date_from'), Tools::getValue('date_to'));

        if (!count($order_invoice_collection)) {
            die($this->trans('No invoice was found.', [], 'Admin.Orderscustomers.Notification'));
        }

        $this->generatePDF($order_invoice_collection, PDF::TEMPLATE_DELIVERY_SLIP);
    }

    /**
     * @deprecated Since 9.0 and will be removed in 10.0
     */
    public function processGenerateSupplyOrderFormPDF()
    {
        @trigger_error(sprintf(
            '%s is deprecated since 9.0 and will be removed in 10.0.',
            __METHOD__
        ), E_USER_DEPRECATED);
        die;
    }

    public function generateDeliverySlipPDFByIdOrderInvoice($id_order_invoice)
    {
        $order_invoice = new OrderInvoice((int) $id_order_invoice);
        if (!Validate::isLoadedObject($order_invoice)) {
            throw new PrestaShopException('Can\'t load Order Invoice object');
        }

        $this->generatePDF($order_invoice, PDF::TEMPLATE_DELIVERY_SLIP);
    }

    public function generateInvoicePDFByIdOrderInvoice($id_order_invoice)
    {
        $order_invoice = new OrderInvoice((int) $id_order_invoice);
        if (!Validate::isLoadedObject($order_invoice)) {
            die($this->trans('The order invoice cannot be found within your database.', [], 'Admin.Orderscustomers.Notification'));
        }

        Hook::exec('actionPDFInvoiceRender', ['order_invoice_list' => [$order_invoice]]);
        $this->generatePDF($order_invoice, PDF::TEMPLATE_INVOICE);
    }

    public function generatePDF($object, $template)
    {
        $pdf = new PDF($object, $template, Context::getContext()->smarty);
        $pdf->render();
    }
}
