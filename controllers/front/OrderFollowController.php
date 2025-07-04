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
use PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderReturnPresenter;
use tools\profiling\Hook;
use tools\profiling\Tools;

class OrderFollowControllerCore extends FrontController
{
    /** @var bool */
    public $auth = true;
    /** @var string */
    public $php_self = 'order-follow';
    /** @var string */
    public $authRedirection = 'order-follow';
    /** @var bool */
    public $ssl = true;

    /**
     * Start forms process.
     *
     * @see FrontController::postProcess()
     */
    public function postProcess(): void
    {
        if (Tools::isSubmit('submitReturnMerchandise')) {
            $order_qte_input = Tools::getValue('order_qte_input');

            if (!$id_order = (int) Tools::getValue('id_order')) {
                Tools::redirect($this->context->link->getPageLink('history'));
            }
            if (!($ids_order_detail = Tools::getValue('ids_order_detail'))) {
                Tools::redirect($this->context->link->getPageLink(
                    'order-detail',
                    null,
                    null,
                    [
                        'id_order' => $id_order,
                        'errorDetail1' => 1,
                    ]
                ));
            }
            if (!$order_qte_input) {
                Tools::redirect($this->context->link->getPageLink(
                    'order-detail',
                    null,
                    null,
                    [
                        'id_order' => $id_order,
                        'errorDetail2' => 1,
                    ]
                ));
            }

            $order = new Order((int) $id_order);
            if (!$order->isReturnable()) {
                Tools::redirect($this->context->link->getPageLink(
                    'order-detail',
                    null,
                    null,
                    [
                        'id_order' => $id_order,
                        'errorNotReturnable' => 1,
                    ]
                ));
            }
            if ($order->id_customer != $this->context->customer->id) {
                Tools::redirect($this->context->link->getPageLink(
                    'order-detail',
                    null,
                    null,
                    [
                        'id_order' => $id_order,
                        'errorNotReturnable' => 1,
                    ]
                ));
            }
            $orderReturn = new OrderReturn();
            $orderReturn->id_customer = (int) $this->context->customer->id;
            $orderReturn->id_order = $id_order;
            $orderReturn->question = htmlspecialchars(Tools::getValue('returnText'));
            if (empty($orderReturn->question)) {
                Tools::redirect($this->context->link->getPageLink(
                    'order-detail',
                    null,
                    null,
                    [
                        'id_order' => $id_order,
                        'errorMsg' => 1,
                        'ids_order_detail' => $ids_order_detail,
                        'order_qte_input' => $order_qte_input,
                    ]
                ));
            }

            if (!$orderReturn->checkEnoughProduct($ids_order_detail, $order_qte_input)) {
                Tools::redirect($this->context->link->getPageLink(
                    'order-detail',
                    null,
                    null,
                    [
                        'id_order' => $id_order,
                        'errorQuantity' => 1,
                    ]
                ));
            }

            $orderReturn->state = 1;
            $orderReturn->add();
            $orderReturn->addReturnDetail($ids_order_detail, $order_qte_input);
            Hook::exec('actionOrderReturn', ['orderReturn' => $orderReturn]);
            Tools::redirect($this->context->link->getPageLink('order-follow'));
        }
    }

    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent(): void
    {
        if ((bool) Configuration::get('PS_ORDER_RETURN') === false) {
            $this->redirect_after = '404';
            $this->redirect();
        }

        if (Configuration::isCatalogMode()) {
            Tools::redirect('index.php');
        }

        $this->context->smarty->assign('ordersReturn', $this->getTemplateVarOrdersReturns());

        parent::initContent();
        $this->setTemplate('customer/order-follow');
    }

    public function getTemplateVarOrdersReturns(): array
    {
        $orders_returns = [];
        $orders_return = OrderReturn::getOrdersReturn($this->context->customer->id);

        $orderReturnPresenter = new OrderReturnPresenter(
            Configuration::get('PS_RETURN_PREFIX', $this->context->language->id),
            $this->context->link
        );

        foreach ($orders_return as $id_order_return => $order_return) {
            $orders_returns[$id_order_return] = $orderReturnPresenter->present($order_return);
        }

        return $orders_returns;
    }

    public function getBreadcrumbLinks(): array
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();
        $breadcrumb['links'][] = [
            'title' => $this->trans('Merchandise returns', [], 'Shop.Theme.Global'),
            'url' => $this->context->link->getPageLink('order-follow'),
        ];

        return $breadcrumb;
    }
}
