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

use PrestaShop\PrestaShop\Core\Search\SearchPanel;
use PrestaShop\PrestaShop\Core\Search\SearchPanelInterface;
use PrestaShop\PrestaShop\Core\Security\Permission;
use tools\profiling\Db;
use tools\profiling\Hook;
use tools\profiling\Module;
use tools\profiling\Tools;

class AdminSearchControllerCore extends AdminController
{
    public const TOKEN_CHECK_START_POS = 34;
    public const TOKEN_CHECK_LENGTH = 8;

    /**
     * @var string
     */
    public $query;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if ($this->isCronTask()
            && substr(
                _COOKIE_KEY_,
                static::TOKEN_CHECK_START_POS,
                static::TOKEN_CHECK_LENGTH
            ) === Tools::getValue('token')
        ) {
            $this->setAllowAnonymous(true);
        }

        parent::init();
    }

    public function getTabSlug()
    {
        return 'ROLE_MOD_TAB_ADMINSEARCHCONF_';
    }

    public function postProcess()
    {
        $this->context = Context::getContext();
        $this->query = trim(Tools::getValue('bo_query'));
        $searchType = (int) Tools::getValue('bo_search_type');

        /* 1.6 code compatibility, as we use HelperList, we need to handle click to go to product */
        $action = Tools::getValue('action');
        if ($action == 'redirectToProduct') {
            $id_product = (int) Tools::getValue('id_product');
            if (Tools::getIsset('statusproduct')) {
                $product = new Product($id_product);
            }

            if (isset($product) && Validate::isLoadedObject($product)) {
                if ($product->toggleStatus()) {
                    $this->confirmations[] = $this->trans('The status has been updated successfully.', [], 'Admin.Notifications.Success');
                } else {
                    $this->errors[] = $this->trans('An error occurred while updating the status.', [], 'Admin.Notifications.Error');
                }
            } else {
                $link = $this->context->link->getAdminLink('AdminProducts', false, ['id_product' => $id_product]);
                Tools::redirectAdmin($link);
            }
        }

        /* Handle empty search field */
        if (!empty($this->query)) {
            if (!$searchType && strlen($this->query) > 1) {
                $this->searchFeatures();
            }

            /* Product research */
            if (!$searchType || $searchType == 1) {
                /* Handle product ID */
                if ($searchType == 1 && Validate::isUnsignedInt((int) $this->query)) {
                    $product = new Product((int) $this->query);
                    if (Validate::isLoadedObject($product)) {
                        Tools::redirectAdmin($this->context->link->getAdminLink('AdminProducts', true, ['id_product' => (int) $product->id, 'updateproduct' => '1']));
                    }
                }

                /* Normal catalog search */
                $this->searchCatalog();
            }

            /* Customer */
            if (!$searchType || $searchType == 2 || $searchType == 6) {
                if (!$searchType || $searchType == 2) {
                    /* Handle customer ID */
                    if ($searchType && Validate::isUnsignedInt((int) $this->query)) {
                        $customer = new Customer((int) $this->query);
                        if (Validate::isLoadedObject($customer)) {
                            Tools::redirectAdmin($this->context->link->getAdminLink(
                                'AdminCustomers',
                                true,
                                [],
                                [
                                    'id_customer' => $customer->id,
                                    'viewcustomer' => 1,
                                ]
                            ));
                        }
                    }

                    /* Normal customer search */
                    $this->searchCustomer();
                }

                if ($searchType == 6) {
                    $this->searchIP();
                }
            }

            /* Order */
            if (!$searchType || $searchType == 3) {
                if (Validate::isUnsignedInt(trim($this->query)) && Validate::isLoadedObject($order = new Order((int) $this->query))) {
                    if ($searchType == 3) {
                        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => (int) $order->id, 'vieworder' => '1']));
                    } else {
                        $row = get_object_vars($order);
                        $row['id_order'] = $row['id'];
                        $customer = $order->getCustomer();
                        $row['customer'] = $customer->firstname . ' ' . $customer->lastname;
                        $order_state = $order->getCurrentOrderState();
                        $row['osname'] = $order_state->name[$this->context->language->id];
                        $this->_list['orders'] = [$row];
                    }
                } else {
                    $orders = Order::getByReference($this->query);
                    $nb_orders = count($orders);
                    if ($nb_orders == 1 && $searchType == 3) {
                        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => (int) $orders[0]->id, 'vieworder' => '1']));
                    } elseif ($nb_orders) {
                        $this->_list['orders'] = [];
                        foreach ($orders as $order) {
                            /** @var Order $order */
                            $row = get_object_vars($order);
                            $row['id_order'] = $row['id'];
                            $customer = $order->getCustomer();
                            $row['customer'] = $customer->firstname . ' ' . $customer->lastname;
                            $order_state = $order->getCurrentOrderState();
                            $row['osname'] = $order_state->name[$this->context->language->id];
                            $this->_list['orders'][] = $row;
                        }
                    } elseif ($searchType == 3) {
                        $this->errors[] = $this->trans('No order was found with this ID:', [], 'Admin.Orderscustomers.Notification') . ' ' . Tools::htmlentitiesUTF8($this->query);
                    }
                }
            }

            /* Invoices */
            if ($searchType == 4) {
                if ($invoice = OrderInvoice::getInvoiceByNumber($this->query)) {
                    Tools::redirectAdmin(
                        $this->context->link->getAdminLink(
                            'AdminPdf',
                            true,
                            [
                                'route' => 'admin_orders_generate_invoice_pdf',
                                'orderId' => (int) $invoice->id_order,
                            ]
                        )
                    );
                }
                $this->errors[] = $this->trans('No invoice was found with this ID:', [], 'Admin.Orderscustomers.Notification') . ' ' . Tools::htmlentitiesUTF8($this->query);
            }

            /* Cart */
            if ($searchType == 5) {
                if (Validate::isUnsignedInt((int) $this->query) && Validate::isLoadedObject($cart = new Cart((int) $this->query))) {
                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders', true, [], ['id_cart' => (int) $cart->id, 'viewcart' => 1]));
                }
                $this->errors[] = $this->trans('No cart was found with this ID:', [], 'Admin.Orderscustomers.Notification') . ' ' . Tools::htmlentitiesUTF8($this->query);
            }
            /* IP */
            // 6 - but it is included in the customer block

            /* Module search */
            if (!$searchType || $searchType == 7) {
                /* Handle module name */
                if ($searchType == 7 && Validate::isModuleName($this->query) && ($module = Module::getInstanceByName($this->query)) && Validate::isLoadedObject($module)) {
                    // @todo redirect directly to module manager with search prefilled, because this won't work anymore
                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], ['tab_module' => $module->tab, 'module_name' => $module->name, 'anchor' => ucfirst($module->name)]));
                }

                /* Normal catalog search */
                $this->searchModule();
            }
        }
        $this->display = 'view';
    }

    public function searchIP()
    {
        if (!ip2long(trim($this->query))) {
            $this->errors[] = $this->trans('This is not a valid IP address:', [], 'Admin.Shopparameters.Notification') . ' ' . Tools::htmlentitiesUTF8($this->query);

            return;
        }
        $this->_list['customers'] = Customer::searchByIp($this->query);
    }

    /**
     * Search a specific string in the products and categories.
     */
    public function searchCatalog()
    {
        $this->context = Context::getContext();
        $this->_list['products'] = Product::searchByName($this->context->language->id, $this->query);
        $this->_list['categories'] = Category::searchByName($this->context->language->id, $this->query);
    }

    /**
     * Search a specific name in the customers.
     */
    public function searchCustomer()
    {
        $this->_list['customers'] = Customer::searchByName($this->query);
    }

    public function searchModule()
    {
        $this->_list['modules'] = [];
        $all_modules = Module::getModulesOnDisk(true, Context::getContext()->employee->id);
        foreach ($all_modules as $module) {
            if (
                (isset($module->name) && stripos($module->name, $this->query) !== false)
                || (isset($module->displayName) && stripos($module->displayName, $this->query) !== false)
                || (isset($module->description) && stripos($module->description, $this->query) !== false)
            ) {
                $module->linkto = Context::getContext()->link->getAdminLink('ADMINMODULESSF') . '&find=' . $module->name;
                $this->_list['modules'][] = $module;
            }
        }
    }

    /**
     * Search a feature in all store.
     */
    public function searchFeatures()
    {
        $this->_list['features'] = [];

        $sql = sprintf(
            'SELECT class_name, name, route_name FROM %stab t INNER JOIN %stab_lang tl ON (t.id_tab = tl.id_tab AND tl.id_lang = %d) WHERE active = 1',
            _DB_PREFIX_,
            _DB_PREFIX_,
            (int) $this->context->employee->id_lang
        );
        $result = Db::getInstance()->executeS($sql);
        $mainControllers = Dispatcher::getControllers([
            _PS_ADMIN_CONTROLLER_DIR_,
            _PS_OVERRIDE_DIR_ . 'controllers/admin/',
        ]);

        foreach ($result as $row) {
            // Search pages with the query need
            if (stripos($row['name'], $this->query) === false) {
                continue;
            }
            // Remove pages without access
            if (!Access::isGranted(Permission::PREFIX_TAB . strtoupper($row['class_name']) . '_READ', $this->context->employee->id_profile)) {
                continue;
            }
            $tab = Tab::getInstanceFromClassName($row['class_name']);
            if (!Validate::isLoadedObject($tab)) {
                continue;
            }
            // Check if it's not a parent tab
            if (!isset($mainControllers[strtolower($row['class_name'])])) {
                $tabs = Tab::getTabs(Context::getContext()->language->id, $tab->id);
                if (isset($tabs[0])) {
                    continue;
                }
            }

            $sfRouteParams = (!empty($row['route_name'])) ? ['route' => $row['route_name']] : [];
            $params = ['bo_query' => $this->query];
            $this->_list['features'][$row['name']][] = [
                'link' => Context::getContext()->link->getAdminLink((string) $row['class_name'], true, $sfRouteParams, $params),
            ];
        }
    }

    protected function initOrderList()
    {
        $this->fields_list['orders'] = [
            'reference' => ['title' => $this->trans('Reference', [], 'Admin.Global'), 'align' => 'center', 'width' => 65],
            'id_order' => ['title' => $this->trans('ID', [], 'Admin.Global'), 'align' => 'center', 'width' => 25],
            'customer' => ['title' => $this->trans('Customer', [], 'Admin.Global')],
            'total_paid_tax_incl' => ['title' => $this->trans('Total', [], 'Admin.Global'), 'width' => 70, 'align' => 'right', 'type' => 'price', 'currency' => true],
            'payment' => ['title' => $this->trans('Payment', [], 'Admin.Global'), 'width' => 100],
            'osname' => ['title' => $this->trans('Status', [], 'Admin.Global'), 'width' => 280],
            'date_add' => ['title' => $this->trans('Date', [], 'Admin.Global'), 'width' => 130, 'align' => 'right', 'type' => 'datetime'],
        ];
    }

    protected function initCustomerList()
    {
        $genders_icon = ['default' => 'unknown.gif'];
        $genders = [0 => $this->trans('?', [], 'Admin.Global')];
        foreach (Gender::getGenders() as $gender) {
            /* @var Gender $gender */
            $genders_icon[$gender->id] = '../genders/' . (int) $gender->id . '.jpg';
            $genders[$gender->id] = $gender->name;
        }
        $this->fields_list['customers'] = [
            'id_customer' => ['title' => $this->trans('ID', [], 'Admin.Global'), 'align' => 'center', 'width' => 25],
            'id_gender' => ['title' => $this->trans('Social title', [], 'Admin.Global'), 'align' => 'center', 'icon' => $genders_icon, 'list' => $genders, 'width' => 25],
            'firstname' => ['title' => $this->trans('First name', [], 'Admin.Global'), 'align' => 'left', 'width' => 150],
            'lastname' => ['title' => $this->trans('Name', [], 'Admin.Global'), 'align' => 'left', 'width' => 'auto'],
            'email' => ['title' => $this->trans('Email address', [], 'Admin.Global'), 'align' => 'left', 'width' => 250],
            'company' => ['title' => $this->trans('Company', [], 'Admin.Global'), 'align' => 'left', 'width' => 150],
            'birthday' => ['title' => $this->trans('Birth date', [], 'Admin.Global'), 'align' => 'center', 'type' => 'date', 'width' => 75],
            'date_add' => ['title' => $this->trans('Registration date', [], 'Admin.Shopparameters.Feature'), 'align' => 'center', 'type' => 'date', 'width' => 75],
            'orders' => ['title' => $this->trans('Orders', [], 'Admin.Global'), 'align' => 'center', 'width' => 50],
            'active' => ['title' => $this->trans('Enabled', [], 'Admin.Global'), 'align' => 'center', 'active' => 'status', 'type' => 'bool', 'width' => 25],
        ];
    }

    protected function initProductList()
    {
        $this->show_toolbar = false;
        $this->fields_list['products'] = [
            'id_product' => ['title' => $this->trans('ID', [], 'Admin.Global'), 'width' => 25],
            'manufacturer_name' => ['title' => $this->trans('Brand', [], 'Admin.Global'), 'align' => 'center', 'width' => 200],
            'reference' => ['title' => $this->trans('Reference', [], 'Admin.Global'), 'align' => 'center', 'width' => 150],
            'name' => ['title' => $this->trans('Name', [], 'Admin.Global'), 'width' => 'auto'],
            'price_tax_excl' => ['title' => $this->trans('Price (tax excl.)', [], 'Admin.Catalog.Feature'), 'align' => 'right', 'type' => 'price', 'width' => 60],
            'price_tax_incl' => ['title' => $this->trans('Price (tax incl.)', [], 'Admin.Catalog.Feature'), 'align' => 'right', 'type' => 'price', 'width' => 60],
            'active' => ['title' => $this->trans('Active', [], 'Admin.Global'), 'width' => 70, 'active' => 'status', 'align' => 'center', 'type' => 'bool'],
        ];
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJqueryPlugin('highlight');
    }

    /* Override because we don't want any buttons */
    public function initToolbar()
    {
    }

    public function initToolbarTitle()
    {
        $this->toolbar_title = $this->trans('Search results', [], 'Admin.Global');
    }

    public function renderView()
    {
        $searchedExpression = Tools::safeOutput($this->query);
        $this->tpl_view_vars['query'] = $searchedExpression;
        $this->tpl_view_vars['show_toolbar'] = true;

        if (!count($this->errors)) {
            $nb_results = 0;
            foreach ($this->_list as $list) {
                if ($list != false) {
                    $nb_results += count($list);
                }
            }
            $this->tpl_view_vars['nb_results'] = $nb_results;

            if ($this->isCountableAndNotEmpty($this->_list, 'features')) {
                $this->tpl_view_vars['features'] = $this->_list['features'];
            }

            if ($this->isCountableAndNotEmpty($this->_list, 'categories')) {
                $categories = [];
                foreach ($this->_list['categories'] as $category) {
                    $categories[] = Tools::getPath(
                        $this->context->link->getAdminLink('AdminCategories', false),
                        $category['id_category']
                    );
                }
                $this->tpl_view_vars['categories'] = $categories;
            }

            if ($this->isCountableAndNotEmpty($this->_list, 'products')) {
                $view = '';
                $this->initProductList();

                $helper = new HelperList();
                $helper->shopLinkType = '';
                $helper->simple_header = true;
                $helper->identifier = 'id_product';
                $helper->actions = ['edit'];
                $helper->show_toolbar = false;
                $helper->table = 'product';
                /* 1.6 code compatibility, as we use HelperList, we need to handle click to go to product, a better way need to be find */
                $helper->currentIndex = $this->context->link->getAdminLink('AdminSearch', false);
                $helper->currentIndex .= '&action=redirectToProduct';

                $query = trim(Tools::getValue('bo_query'));
                $searchType = (int) Tools::getValue('bo_search_type');

                if ($query) {
                    $helper->currentIndex .= '&bo_query=' . $query . '&bo_search_type=' . $searchType;
                }

                $helper->token = Tools::getAdminTokenLite('AdminSearch');

                if ($this->_list['products']) {
                    $view = $helper->generateList($this->_list['products'], $this->fields_list['products']);
                }

                $this->tpl_view_vars['products'] = $view;
                $this->tpl_view_vars['productsCount'] = count($this->_list['products']);
            }

            if ($this->isCountableAndNotEmpty($this->_list, 'customers')) {
                $view = '';
                $this->initCustomerList();

                $helper = new HelperList();
                $helper->shopLinkType = '';
                $helper->simple_header = true;
                $helper->identifier = 'id_customer';
                $helper->actions = ['edit', 'view'];
                $helper->show_toolbar = false;
                $helper->table = 'customer';
                $helper->currentIndex = $this->context->link->getAdminLink('AdminCustomers', false);
                $helper->token = Tools::getAdminTokenLite('AdminCustomers');

                foreach ($this->_list['customers'] as $key => $val) {
                    $this->_list['customers'][$key]['orders'] = Order::getCustomerNbOrders((int) $val['id_customer']);
                }

                $view = $helper->generateList($this->_list['customers'], $this->fields_list['customers']);
                $this->tpl_view_vars['customers'] = $view;
                $this->tpl_view_vars['customerCount'] = count($this->_list['customers']);
            }

            if ($this->isCountableAndNotEmpty($this->_list, 'orders')) {
                $this->initOrderList();

                $helper = new HelperList();
                $helper->shopLinkType = '';
                $helper->simple_header = true;
                $helper->identifier = 'id_order';
                $helper->actions = ['view'];
                $helper->show_toolbar = false;
                $helper->table = 'order';
                $helper->currentIndex = $this->context->link->getAdminLink('AdminOrders', false);
                $helper->token = Tools::getAdminTokenLite('AdminOrders');

                $this->tpl_view_vars['orders'] = $helper->generateList($this->_list['orders'], $this->fields_list['orders']);
                $this->tpl_view_vars['orderCount'] = count($this->_list['orders']);
            }

            if ($this->isCountableAndNotEmpty($this->_list, 'modules')) {
                $this->tpl_view_vars['modules'] = $this->_list['modules'];
            }
        }
        $this->getSearchPanels($searchedExpression);

        return parent::renderView();
    }

    protected function getSearchPanels(string $searchedExpression): void
    {
        // Build native search panels
        $searchPanels = [];
        $searchPanels[] = new SearchPanel(
            $this->trans('Search docs.prestashop-project.org', [], 'Admin.Navigation.Search'),
            $this->trans('Go to the documentation', [], 'Admin.Navigation.Search'),
            'https://docs.prestashop-project.org/welcome/',
            [
                'q' => $searchedExpression,
            ]
        );

        // Get additional search panels from hooks
        // An array [module_name => module_output] will be returned
        $alternativeSearchPanelsFromModules = Hook::exec(
            'actionGetAlternativeSearchPanels',
            [
                'previous_search_panels' => $searchPanels,
                'bo_query' => $searchedExpression,
            ],
            null,
            true
        );

        foreach ($alternativeSearchPanelsFromModules as $alternativeSearchPanelsFromModule) {
            foreach ($alternativeSearchPanelsFromModule as $alternativeSearchPanel) {
                if ($alternativeSearchPanel instanceof SearchPanelInterface) {
                    $searchPanels[] = $alternativeSearchPanel;
                }
            }
        }

        // Transform the search panels and inject them to the view
        $this->tpl_view_vars['searchPanels'] = [];
        foreach ($searchPanels as $searchPanel) {
            $this->tpl_view_vars['searchPanels'][] = [
                'title' => $searchPanel->getTitle(),
                'button_label' => $searchPanel->getButtonLabel(),
                'link' => $searchPanel->getLink(),
                'is_external_link' => $searchPanel->isExternalLink(),
            ];
        }
    }

    /**
     * Check if key is present in array, is countable and has data.
     *
     * @param array $array Array
     * @param string $key Key
     *
     * @return bool
     */
    protected function isCountableAndNotEmpty(array $array, string $key)
    {
        return isset($array[$key])
            && is_countable($array[$key])
            && count($array[$key]);
    }

    /**
     * Request triggering the search indexation.
     *
     * Kept as GET request for backward compatibility purpose, but should be modified as POST when migrated.
     * NOTE the token is different for that method, check the method checkToken() for more details.
     */
    public function displayAjaxSearchCron()
    {
        if (!Tools::getValue('id_shop')) {
            Context::getContext()->shop->setContext(Shop::CONTEXT_ALL);
        } else {
            Context::getContext()->shop->setContext(Shop::CONTEXT_SHOP, (int) Tools::getValue('id_shop'));
        }

        // Considering the indexing task can be really long, we ask the PHP process to not stop before 2 hours.
        ini_set('max_execution_time', '7200');
        Search::indexation(Tools::getValue('full'));

        /*
         * If redirect parameter was specified in the URL and we have the URL the user came from,
         * we will redirect him back. This is used in backoffice so the user lands back to the config form.
         *
         * In CRON or CLI (referer is missing), it will die here. Even if redirect is in the URL.
         */
        if (Tools::getValue('redirect') && !empty($_SERVER['HTTP_REFERER'])) {
            Tools::redirectAdmin($_SERVER['HTTP_REFERER'] . '&conf=4');
        }
    }

    /**
     * Check if a task is a cron task
     *
     * @return bool
     */
    protected function isCronTask()
    {
        return Tools::isSubmit('action') && 'searchCron' === Tools::getValue('action');
    }
}
