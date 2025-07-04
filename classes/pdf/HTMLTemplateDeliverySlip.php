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

use PrestaShop\PrestaShop\Core\Util\Sorter;
use tools\profiling\Tools;

/**
 * @since 1.5
 */
class HTMLTemplateDeliverySlipCore extends HTMLTemplate
{
    /**
     * @var Order
     */
    public $order;

    /** @var OrderInvoice Order invoice */
    public $order_invoice;

    /**
     * @param OrderInvoice $order_invoice
     * @param Smarty $smarty
     * @param bool $bulk_mode
     *
     * @throws PrestaShopException
     */
    public function __construct(OrderInvoice $order_invoice, Smarty $smarty, $bulk_mode = false)
    {
        $this->order_invoice = $order_invoice;
        $this->order = new Order($this->order_invoice->id_order);
        $this->smarty = $smarty;

        // If shop_address is null, then update it with current one.
        // But no DB save required here to avoid massive updates for bulk PDF generation case.
        // (DB: bug fixed in 1.6.1.1 with upgrade SQL script to avoid null shop_address in old orderInvoices)
        if (empty($this->order_invoice->shop_address)) {
            $this->order_invoice->shop_address = OrderInvoice::getCurrentFormattedShopAddress((int) $this->order->id_shop);
            if (!$bulk_mode) {
                OrderInvoice::fixAllShopAddresses();
            }
        }

        // header informations
        // The date MUST be the delivery slip date and not the invoice date …
        // In case of empty date, use the old one …
        $this->date = Tools::displayDate($order_invoice->delivery_date) ?: Tools::displayDate($order_invoice->date_add);
        $prefix = Configuration::get('PS_DELIVERY_PREFIX', Context::getContext()->language->id);
        $this->title = sprintf(HTMLTemplateDeliverySlip::l('%1$s%2$06d'), $prefix, $this->order_invoice->delivery_number);

        // footer informations
        $this->shop = new Shop((int) $this->order->id_shop);
    }

    /**
     * Returns the template's HTML header.
     *
     * @return string HTML header
     */
    public function getHeader()
    {
        $this->assignCommonHeaderData();
        $this->smarty->assign(['header' => Context::getContext()->getTranslator()->trans('Delivery', [], 'Shop.Pdf')]);

        return $this->smarty->fetch($this->getTemplate('header'));
    }

    /**
     * Returns the template's HTML content.
     *
     * @return string HTML content
     */
    public function getContent()
    {
        $delivery_address = new Address((int) $this->order->id_address_delivery);
        $formatted_delivery_address = AddressFormat::generateAddress($delivery_address, [], '<br />', ' ');
        $formatted_invoice_address = '';

        if ($this->order->id_address_delivery != $this->order->id_address_invoice) {
            $invoice_address = new Address((int) $this->order->id_address_invoice);
            $formatted_invoice_address = AddressFormat::generateAddress($invoice_address, [], '<br />', ' ');
        }

        $carrier = new Carrier($this->order->id_carrier);
        $carrier->name = ($carrier->name == '0' ? Configuration::get('PS_SHOP_NAME') : $carrier->name);

        $order_details = $this->order_invoice->getProducts();
        if (Configuration::get('PS_PDF_IMG_DELIVERY')) {
            foreach ($order_details as &$order_detail) {
                if ($order_detail['image'] != null) {
                    $name = 'product_mini_' . (int) $order_detail['product_id'] . (isset($order_detail['product_attribute_id']) ? '_' . (int) $order_detail['product_attribute_id'] : '') . '.jpg';
                    $path = _PS_PRODUCT_IMG_DIR_ . $order_detail['image']->getExistingImgPath() . '.jpg';

                    $order_detail['image_tag'] = preg_replace(
                        '/\.*' . preg_quote(__PS_BASE_URI__, '/') . '/',
                        _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR,
                        ImageManager::thumbnail($path, $name, 45, 'jpg', false),
                        1
                    );

                    if (file_exists(_PS_TMP_IMG_DIR_ . $name)) {
                        $order_detail['image_size'] = getimagesize(_PS_TMP_IMG_DIR_ . $name);
                    } else {
                        $order_detail['image_size'] = false;
                    }
                }
            }
        }

        // Sort products by Reference ID (and if equals (like combination) by Supplier Reference)
        $sorter = new Sorter();
        $order_details = $sorter->natural($order_details, Sorter::ORDER_DESC, 'product_reference', 'product_supplier_reference');

        $this->smarty->assign([
            'order' => $this->order,
            'order_details' => $order_details,
            'delivery_address' => $formatted_delivery_address,
            'invoice_address' => $formatted_invoice_address,
            'order_invoice' => $this->order_invoice,
            'carrier' => $carrier,
            'display_product_images' => Configuration::get('PS_PDF_IMG_DELIVERY'),
        ]);

        $tpls = [
            'style_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.style-tab')),
            'addresses_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.addresses-tab')),
            'summary_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.summary-tab')),
            'product_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.product-tab')),
            'payment_tab' => $this->smarty->fetch($this->getTemplate('delivery-slip.payment-tab')),
        ];
        $this->smarty->assign($tpls);

        return $this->smarty->fetch($this->getTemplate('delivery-slip'));
    }

    /**
     * Returns the template filename when using bulk rendering.
     *
     * @return string filename
     */
    public function getBulkFilename()
    {
        return 'deliveries.pdf';
    }

    /**
     * Returns the template filename.
     *
     * @return string filename
     */
    public function getFilename()
    {
        return Configuration::get('PS_DELIVERY_PREFIX', Context::getContext()->language->id, null, $this->order->id_shop) . sprintf('%06d', $this->order_invoice->delivery_number) . '.pdf';
    }
}
