<?php

use tools\profiling\Db;
use tools\profiling\Hook;
use tools\profiling\ObjectModel;
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
class WebserviceRequestCore
{
    public const HTTP_GET = 1;
    public const HTTP_POST = 2;
    public const HTTP_PUT = 4;
    public const HTTP_PATCH = 8;

    protected $_available_languages = null;
    /**
     * Errors triggered at execution.
     *
     * @var array
     */
    public $errors = [];

    /**
     * Set if return should display content or not.
     *
     * @var bool
     */
    protected $_outputEnabled = true;

    /**
     * Set if the management is specific or if it is classic (entity management)
     *
     * @var WebserviceSpecificManagementImages|WebserviceSpecificManagementSearch|WebserviceSpecificManagementAttachments|false
     */
    protected $objectSpecificManagement = false;

    /**
     * Base PrestaShop webservice URL.
     *
     * @var string
     */
    public $wsUrl;

    /**
     * Set if the authentication key was checked.
     *
     * @var bool
     */
    protected $_authenticated = false;

    /**
     * HTTP Method to support.
     *
     * @var string
     */
    public $method;

    /**
     * The segment of the URL.
     *
     * @var array
     */
    public $urlSegment = [];

    /**
     * The segment list of the URL after the "api" segment.
     *
     * @var array
     */
    public $urlFragments = [];

    /**
     * The time in microseconds of the start of the execution of the web service request.
     *
     * @var float
     */
    protected $_startTime = 0;

    /**
     * The list of each resources manageable via web service.
     *
     * @var array
     */
    public $resourceList;

    /**
     * The configuration parameters of the current resource.
     *
     * @var array
     */
    public $resourceConfiguration;

    /**
     * The permissions for the current key.
     *
     * @var array
     */
    public $keyPermissions;

    /**
     * The XML string to display if web service call succeed.
     *
     * @var string
     */
    protected $specificOutput = '';

    /**
     * The list of objects to display.
     *
     * @var array
     */
    public $objects;

    /**
     * The current object to support, it extends the PrestaShop ObjectModel.
     *
     * @var ObjectModel
     */
    protected $_object;

    /**
     * The schema to display.
     * If null, no schema have to be displayed and normal management has to be performed.
     *
     * @var string
     */
    public $schemaToDisplay;

    /**
     * The fields to display. These fields will be displayed when retrieving objects.
     *
     * @var string
     */
    public $fieldsToDisplay = 'minimum';

    /**
     * If we are in PUT or POST case, we use this attribute to store the xml string value during process.
     *
     * @var string
     */
    protected $_inputXml;

    /**
     * Object instance for singleton.
     *
     * @var WebserviceRequest|null
     */
    protected static $_instance;

    /**
     * Key used for authentication.
     *
     * @var string
     */
    protected $_key;

    /**
     * This is used to have a deeper tree diagram.
     *
     * @var int
     */
    public $depth = 0;

    /**
     * Name of the output format.
     *
     * @var string
     */
    protected $outputFormat = 'xml';

    /**
     * The object to build the output.
     *
     * @var WebserviceOutputBuilder|null
     */
    protected $objOutput;

    /**
     * Save the class name for override used in getInstance().
     *
     * @var string
     */
    public static $ws_current_classname;

    /**
     * @var array
     */
    public $params;

    /**
     * @var array the list of shop ids, can be empty
     */
    public static $shopIDs = [];

    public function getOutputEnabled()
    {
        return $this->_outputEnabled;
    }

    public function setOutputEnabled(bool $bool)
    {
        $this->_outputEnabled = $bool;

        return $this;
    }

    /**
     * Get WebserviceRequest object instance (Singleton).
     *
     * @return object WebserviceRequest instance
     */
    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new WebserviceRequest::$ws_current_classname();
        }

        return self::$_instance;
    }

    public static function resetStaticCache(): void
    {
        static::$_instance = null;
    }

    protected function getOutputObject($type)
    {
        // set header param in header or as get param
        $headers = self::getallheaders();
        if (isset($headers['Io-Format'])) {
            $type = $headers['Io-Format'];
        } elseif (isset($headers['Output-Format'])) {
            $type = $headers['Output-Format'];
        } elseif (isset($_GET['output_format'])) {
            $type = $_GET['output_format'];
        } elseif (isset($_GET['io_format'])) {
            $type = $_GET['io_format'];
        }
        $this->outputFormat = $type;
        switch ($type) {
            case 'JSON':
                require_once __DIR__ . '/WebserviceOutputJSON.php';
                $obj_render = new WebserviceOutputJSON();

                break;
            case 'XML':
            default:
                $obj_render = new WebserviceOutputXML();

                break;
        }

        return $obj_render;
    }

    public static function getResources()
    {
        $resources = [
            'addresses' => ['description' => 'The Customer, Brand and Customer addresses', 'class' => 'Address'],
            'attachments' => ['description' => 'The product Attachments', 'class' => 'Attachment', 'specific_management' => true],
            'carriers' => ['description' => 'The Carriers', 'class' => 'Carrier'],
            'carts' => ['description' => 'Customer\'s carts', 'class' => 'Cart'],
            'cart_rules' => ['description' => 'Cart rules management', 'class' => 'CartRule'],
            'categories' => ['description' => 'The product categories', 'class' => 'Category'],
            'combinations' => ['description' => 'The product combinations', 'class' => 'Combination'],
            'configurations' => ['description' => 'Shop configuration', 'class' => 'Configuration'],
            'contacts' => ['description' => 'Shop contacts', 'class' => 'Contact'],
            'countries' => ['description' => 'The countries', 'class' => 'Country'],
            'currencies' => ['description' => 'The currencies', 'class' => 'Currency'],
            'customers' => ['description' => 'The e-shop\'s customers', 'class' => 'Customer'],
            'customer_threads' => ['description' => 'Customer services threads', 'class' => 'CustomerThread'],
            'customer_messages' => ['description' => 'Customer services messages', 'class' => 'CustomerMessage'],
            'deliveries' => ['description' => 'Product delivery', 'class' => 'Delivery'],
            'groups' => ['description' => 'The customer\'s groups', 'class' => 'Group'],
            'guests' => ['description' => 'The guests', 'class' => 'Guest'],
            'images' => ['description' => 'The images', 'specific_management' => true],
            'image_types' => ['description' => 'The image types', 'class' => 'ImageType'],
            'languages' => ['description' => 'Shop languages', 'class' => 'Language'],
            'manufacturers' => ['description' => 'The product brands', 'class' => 'Manufacturer'],
            'messages' => ['description' => 'The Messages', 'class' => 'Message'],
            'order_carriers' => ['description' => 'The Order carriers', 'class' => 'OrderCarrier'],
            'order_cart_rules' => ['description' => 'The Order cart rules', 'class' => 'OrderCartRule'],
            'order_details' => ['description' => 'Details of an order', 'class' => 'OrderDetail'],
            'order_histories' => ['description' => 'The Order histories', 'class' => 'OrderHistory'],
            'order_invoices' => ['description' => 'The Order invoices', 'class' => 'OrderInvoice'],
            'orders' => ['description' => 'The Customers orders', 'class' => 'Order'],
            'order_payments' => ['description' => 'The Order payments', 'class' => 'OrderPayment'],
            'order_returns' => ['description' => 'The Customers orders returns', 'class' => 'OrderReturn'],
            'order_states' => ['description' => 'The Order statuses', 'class' => 'OrderState'],
            'order_slip' => ['description' => 'The Order slips', 'class' => 'OrderSlip'],
            'price_ranges' => ['description' => 'Price ranges', 'class' => 'RangePrice'],
            'product_features' => ['description' => 'The product features', 'class' => 'Feature'],
            'product_feature_values' => ['description' => 'The product feature values', 'class' => 'FeatureValue'],
            'product_options' => ['description' => 'The product options', 'class' => 'AttributeGroup'],
            'product_option_values' => ['description' => 'The product options value', 'class' => 'ProductAttribute'],
            'products' => ['description' => 'The products', 'class' => 'Product'],
            'states' => ['description' => 'The available states of countries', 'class' => 'State'],
            'stores' => ['description' => 'The stores', 'class' => 'Store'],
            'suppliers' => ['description' => 'The product suppliers', 'class' => 'Supplier'],
            'tags' => ['description' => 'The Products tags', 'class' => 'Tag'],
            'translated_configurations' => ['description' => 'Shop configuration', 'class' => 'TranslatedConfiguration'],
            'weight_ranges' => ['description' => 'Weight ranges', 'class' => 'RangeWeight'],
            'zones' => ['description' => 'The Countries zones', 'class' => 'Zone'],
            'employees' => ['description' => 'The Employees', 'class' => 'Employee'],
            'search' => ['description' => 'Search', 'specific_management' => true, 'forbidden_method' => ['PUT', 'POST', 'PATCH', 'DELETE']],
            'content_management_system' => ['description' => 'Content management system', 'class' => 'CMS'],
            'shops' => ['description' => 'Shops from multi-shop feature', 'class' => 'Shop'],
            'shop_groups' => ['description' => 'Shop groups from multi-shop feature', 'class' => 'ShopGroup'],
            'taxes' => ['description' => 'The tax rate', 'class' => 'Tax'],
            'stock_movements' => ['description' => 'Stock movements', 'class' => 'StockMvtWS', 'forbidden_method' => ['PUT', 'POST', 'PATCH', 'DELETE']],
            'stock_movement_reasons' => ['description' => 'Stock movement reason', 'class' => 'StockMvtReason'],
            'warehouses' => ['description' => 'Warehouses', 'class' => 'Warehouse', 'forbidden_method' => ['DELETE']],
            'stocks' => ['description' => 'Stocks', 'class' => 'Stock', 'forbidden_method' => ['PUT', 'POST', 'PATCH', 'DELETE']],
            'stock_availables' => ['description' => 'Available quantities', 'class' => 'StockAvailable', 'forbidden_method' => ['POST', 'DELETE']],
            'product_suppliers' => ['description' => 'Product Suppliers', 'class' => 'ProductSupplier'],
            'tax_rules' => ['description' => 'Tax rules entity', 'class' => 'TaxRule'],
            'tax_rule_groups' => ['description' => 'Tax rule groups', 'class' => 'TaxRulesGroup'],
            'specific_prices' => ['description' => 'Specific price management', 'class' => 'SpecificPrice'],
            'specific_price_rules' => ['description' => 'Specific price management', 'class' => 'SpecificPriceRule'],
            'shop_urls' => ['description' => 'Shop URLs from multi-shop feature', 'class' => 'ShopUrl'],
            'product_customization_fields' => ['description' => 'Customization Field', 'class' => 'CustomizationField'],
            'customizations' => ['description' => 'Customization values', 'class' => 'Customization'],
        ];

        // An array [module_name => module_output] will be returned
        // Hook call is ignoring exceptions set in the backoffice
        $extra_resources = Hook::exec('addWebserviceResources', ['resources' => $resources], null, true, false);
        if (is_countable($extra_resources) && count($extra_resources)) {
            foreach ($extra_resources as $new_resources) {
                if (is_countable($new_resources) && count($new_resources)) {
                    $resources = array_merge($resources, $new_resources);
                }
            }
        }
        ksort($resources);

        return $resources;
    }

    /**
     * This method is used for calculate the price for products on the output details.
     *
     * @param array $field
     * @param ObjectModel $entity_object
     *
     * @return array field parameters
     */
    public function getPriceForProduct($field, $entity_object)
    {
        if (is_int($entity_object->id)) {
            $arr_return = $this->specificPriceForProduct($entity_object, ['default_price' => '']);
            $field['value'] = $arr_return['default_price']['value'];
        }

        return $field;
    }

    /**
     * This method is used for calculate the price for products on a virtual fields.
     *
     * @param ObjectModel $entity_object
     * @param array $parameters
     *
     * @return array
     */
    public function specificPriceForProduct($entity_object, $parameters)
    {
        foreach (array_keys($parameters) as $name) {
            $parameters[$name]['object_id'] = $entity_object->id;
        }
        $arr_return = $this->specificPriceCalculation($parameters);

        return $arr_return;
    }

    public function specificPriceCalculation($parameters)
    {
        $arr_return = [];
        foreach ($parameters as $name => $value) {
            $id_shop = (int) Context::getContext()->shop->id;
            $id_country = (int) (isset($value['country']) ? $value['country'] : (Configuration::get('PS_COUNTRY_DEFAULT')));
            $id_state = (int) (isset($value['state']) ? $value['state'] : 0);
            $id_currency = (int) (isset($value['currency']) ? $value['currency'] : Currency::getDefaultCurrencyId());
            $id_group = (int) (isset($value['group']) ? $value['group'] : (int) Configuration::get('PS_CUSTOMER_GROUP'));
            $quantity = (int) (isset($value['quantity']) ? $value['quantity'] : 1);
            $use_tax = (bool) (isset($value['use_tax']) ? $value['use_tax'] : Configuration::get('PS_TAX'));
            $decimals = (int) (isset($value['decimals']) ? $value['decimals'] : Configuration::get('PS_PRICE_ROUND_MODE'));
            $id_product_attribute = (int) (isset($value['product_attribute']) ? $value['product_attribute'] : null);
            $only_reduc = (bool) (isset($value['only_reduction']) ? $value['only_reduction'] : false);
            $use_reduc = (bool) (isset($value['use_reduction']) ? $value['use_reduction'] : true);
            $use_ecotax = (bool) (isset($value['use_ecotax']) ? $value['use_ecotax'] : Configuration::get('PS_USE_ECOTAX'));
            $specific_price_output = null;
            $id_county = (string) (isset($value['county']) ? $value['county'] : 0);
            $return_value = Product::priceCalculation(
                $id_shop,
                $value['object_id'],
                $id_product_attribute,
                $id_country,
                $id_state,
                $id_county,
                $id_currency,
                $id_group,
                $quantity,
                $use_tax,
                $decimals,
                $only_reduc,
                $use_reduc,
                $use_ecotax,
                $specific_price_output,
                false
            );
            $arr_return[$name] = ['sqlId' => strtolower($name), 'value' => sprintf('%f', $return_value)];
        }

        return $arr_return;
    }

    /**
     * This method is used for calculate the price for products on a virtual fields.
     *
     * @param ObjectModel $entity_object
     * @param array $parameters
     *
     * @return array
     */
    public function specificPriceForCombination($entity_object, $parameters)
    {
        foreach (array_keys($parameters) as $name) {
            $parameters[$name]['object_id'] = $entity_object->id_product;
            $parameters[$name]['product_attribute'] = $entity_object->id;
        }
        $arr_return = $this->specificPriceCalculation($parameters);

        return $arr_return;
    }

    /**
     * Start Webservice request
     * Check webservice activation
     * Check authentication
     * Check resource
     * Check HTTP Method
     * Execute the action
     * Display the result.
     *
     * @param string $key
     * @param string $method
     * @param string $url
     * @param array $params
     * @param string $bad_class_name
     * @param string $inputXml
     *
     * @return array Returns an array of results (headers, content, type of resource...)
     */
    public function fetch($key, $method, $url, $params, $bad_class_name, $inputXml = null)
    {
        // Time logger
        $this->_startTime = microtime(true);
        $this->objects = [];

        // Error handler
        set_error_handler([$this, 'webserviceErrorHandler']);
        ini_set('html_errors', 'off');

        // Two global vars, for compatibility with the PS core
        global $webservice_call, $display_errors;
        $webservice_call = true;
        $display_errors = strtolower(ini_get('display_errors')) != 'off';
        // __PS_BASE_URI__ is from Shop::$current_base_uri
        $this->wsUrl = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'api/';
        // set the output object which manage the content and header structure and information
        $this->objOutput = new WebserviceOutputBuilder($this->wsUrl);

        $this->_key = trim($key);

        if (isset($params['output_format'])) {
            $this->outputFormat = $params['output_format'];
        }
        // Set the render object to build the output on the asked format (XML, JSON, CSV, ...)
        $this->objOutput->setObjectRender($this->getOutputObject($this->outputFormat));
        $this->params = $params;
        // Check webservice activation and request authentication
        if ($this->webserviceChecks()) {
            if ($bad_class_name) {
                $this->setError(500, 'Class "' . htmlspecialchars($bad_class_name) . '" not found. Please update the class_name field in the webservice_account table.', 126);
            }
            // parse request url
            $this->method = $method;
            $this->urlSegment = explode('/', $url);
            $this->urlFragments = $params;
            $this->_inputXml = $inputXml;
            $this->depth = isset($this->urlFragments['depth']) ? (int) $this->urlFragments['depth'] : $this->depth;

            try {
                // Method below set a particular fonction to use on the price field for products entity
                // @see WebserviceRequest::getPriceForProduct() method
                // @see WebserviceOutputBuilder::setSpecificField() method
                // $this->objOutput->setSpecificField($this, 'getPriceForProduct', 'price', 'products');
                if (isset($this->urlFragments['price'])) {
                    $this->objOutput->setVirtualField($this, 'specificPriceForCombination', 'combinations', $this->urlFragments['price']);
                    $this->objOutput->setVirtualField($this, 'specificPriceForProduct', 'products', $this->urlFragments['price']);
                }
            } catch (Exception $e) {
                $this->setError(500, $e->getMessage(), $e->getCode());
            }

            if (isset($this->urlFragments['language'])) {
                $this->_available_languages = $this->filterLanguage();
            } else {
                $this->_available_languages = Language::getIDs();
            }

            if (empty($this->_available_languages)) {
                $this->setError(400, 'language is not available', 81);
            }

            // Need to set available languages for the render object.
            // Thus we can filter i18n field for the output
            // @see WebserviceOutputXML::renderField() method for example
            $this->objOutput->objectRender->setLanguages($this->_available_languages);

            // check method and resource
            if (empty($this->errors) && $this->checkResource() && $this->checkHTTPMethod()) {
                // The resource list is necessary for build the output
                $this->objOutput->setWsResources($this->resourceList);

                // if the resource is a core entity...
                if (!isset($this->resourceList[$this->urlSegment[0]]['specific_management']) || !$this->resourceList[$this->urlSegment[0]]['specific_management']) {
                    // load resource configuration
                    if ($this->urlSegment[0] != '') {
                        /** @var ObjectModel $object */
                        $object = new $this->resourceList[$this->urlSegment[0]]['class']();
                        if (isset($this->resourceList[$this->urlSegment[0]]['parameters_attribute'])) {
                            $this->resourceConfiguration = $object->getWebserviceParameters($this->resourceList[$this->urlSegment[0]]['parameters_attribute']);
                        } else {
                            $this->resourceConfiguration = $object->getWebserviceParameters();
                        }
                    }
                    $success = false;
                    // execute the action
                    switch ($this->method) {
                        case 'GET':
                        case 'HEAD':
                            if ($this->executeEntityGetAndHead()) {
                                $success = true;
                            }

                            break;
                        case 'POST':
                            if ($this->executeEntityPost()) {
                                $success = true;
                            }

                            break;
                        case 'PUT':
                            if ($this->executeEntityPut()) {
                                $success = true;
                            }

                            break;
                        case 'PATCH':
                            if ($this->executeEntityPatch()) {
                                $success = true;
                            }

                            break;
                        case 'DELETE':
                            $this->executeEntityDelete();

                            break;
                    }
                    // Need to set an object for the WebserviceOutputBuilder object in any case
                    // because schema need to get webserviceParameters of this object
                    if (isset($object)) {
                        $this->objects['empty'] = $object;
                    }
                } else {
                    // if the management is specific
                    $specificObjectName = 'WebserviceSpecificManagement' . ucfirst(Tools::toCamelCase($this->urlSegment[0]));
                    if (!class_exists($specificObjectName)) {
                        $this->setError(501, sprintf('The specific management class is not implemented for the "%s" entity.', $this->urlSegment[0]), 124);
                    } else {
                        $this->setObjectSpecificManagement(new $specificObjectName());
                        $this->objectSpecificManagement->setObjectOutput($this->objOutput);
                        if ($this instanceof WebserviceRequest) {
                            $this->objectSpecificManagement->setWsObject($this);
                        }

                        try {
                            $this->objectSpecificManagement->manage();
                        } catch (WebserviceException $e) {
                            if ($e->getType() == WebserviceException::DID_YOU_MEAN) {
                                $this->setErrorDidYouMean($e->getStatus(), $e->getMessage(), $e->getWrongValue(), $e->getAvailableValues(), $e->getCode());
                            } elseif ($e->getType() == WebserviceException::SIMPLE) {
                                $this->setError($e->getStatus(), $e->getMessage(), $e->getCode());
                            }
                        }
                    }
                }
            }
        }
        $return = $this->returnOutput();
        unset(
            $webservice_call,
            $display_errors
        );

        return $return;
    }

    protected function webserviceChecks()
    {
        return $this->isActivated() && $this->authenticate() && $this->groupShopExists($this->params) && $this->shopExists($this->params) && $this->shopHasRight($this->_key);
    }

    /**
     * Set a webservice error.
     *
     * @param int $status
     * @param string $label
     * @param int $code
     */
    public function setError($status, $label, $code)
    {
        global $display_errors;
        if (!isset($display_errors)) {
            $display_errors = strtolower(ini_get('display_errors')) != 'off';
        }
        if (isset($this->objOutput)) {
            $this->objOutput->setStatus($status);
        }
        $this->errors[] = [$code, $label];
    }

    /**
     * Set a webservice error and propose a new value near from the available values.
     *
     * @param int $num
     * @param string $label
     * @param string $value
     * @param array $available_values
     * @param int $code
     */
    public function setErrorDidYouMean($num, $label, $value, $available_values, $code)
    {
        $this->setError($num, $label . '. Did you mean: "' . $this->getClosest($value, $available_values) . '"?' . (count($available_values) > 1 ? ' The full list is: "' . implode('", "', $available_values) . '"' : ''), $code);
    }

    /**
     * Return the nearest value picked in the values list.
     *
     * @param string $input
     * @param array $words
     *
     * @return string
     */
    protected function getClosest($input, $words)
    {
        $shortest = -1;
        $closest = '';
        foreach ($words as $word) {
            $lev = levenshtein($input, $word);
            if ($lev == 0) {
                $closest = $word;
                $shortest = 0;

                break;
            }
            if ($lev <= $shortest || $shortest < 0) {
                $closest = $word;
                $shortest = $lev;
            }
        }

        return $closest;
    }

    /**
     * Used to replace the default PHP error handler, in order to display PHP errors in a XML format.
     *
     * @param int $errno contains the level of the error raised, as an integer
     * @param string $errstr contains the error message, as a string
     * @param string $errfile errfile, which contains the filename that the error was raised in, as a string
     * @param int $errline errline, which contains the line number the error was raised at, as an integer
     *
     * @return bool Always return true to avoid the default PHP error handler
     */
    public function webserviceErrorHandler($errno, $errstr, $errfile, $errline)
    {
        $display_errors = strtolower(ini_get('display_errors')) != 'off';
        if (!(error_reporting() & $errno) || $display_errors) {
            return true;
        }

        $errortype = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'Error',
            E_USER_WARNING => 'User warning',
            E_USER_NOTICE => 'User notice',
            E_RECOVERABLE_ERROR => 'Recoverable error',
        ];
        $type = $errortype[$errno] ?? 'Unknown error';
        error_log('[PHP ' . $type . ' #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')');

        switch ($errno) {
            case E_ERROR:
                WebserviceRequest::getInstance()->setError(500, '[PHP Error #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 2);

                break;
            case E_WARNING:
                WebserviceRequest::getInstance()->setError(500, '[PHP Warning #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 3);

                break;
            case E_PARSE:
                WebserviceRequest::getInstance()->setError(500, '[PHP Parse #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 4);

                break;
            case E_NOTICE:
                WebserviceRequest::getInstance()->setError(500, '[PHP Notice #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 5);

                break;
            case E_CORE_ERROR:
                WebserviceRequest::getInstance()->setError(500, '[PHP Core #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 6);

                break;
            case E_CORE_WARNING:
                WebserviceRequest::getInstance()->setError(500, '[PHP Core warning #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 7);

                break;
            case E_COMPILE_ERROR:
                WebserviceRequest::getInstance()->setError(500, '[PHP Compile #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 8);

                break;
            case E_COMPILE_WARNING:
                WebserviceRequest::getInstance()->setError(500, '[PHP Compile warning #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 9);

                break;
            case E_USER_ERROR:
                WebserviceRequest::getInstance()->setError(500, '[PHP Error #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 10);

                break;
            case E_USER_WARNING:
                WebserviceRequest::getInstance()->setError(500, '[PHP User warning #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 11);

                break;
            case E_USER_NOTICE:
                WebserviceRequest::getInstance()->setError(500, '[PHP User notice #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 12);

                break;
            case E_STRICT:
                WebserviceRequest::getInstance()->setError(500, '[PHP Strict #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 13);

                break;
            case E_RECOVERABLE_ERROR:
                WebserviceRequest::getInstance()->setError(500, '[PHP Recoverable error #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 14);

                break;
            default:
                WebserviceRequest::getInstance()->setError(500, '[PHP Unknown error #' . $errno . '] ' . $errstr . ' (' . $errfile . ', line ' . $errline . ')', 15);
        }

        return true;
    }

    /**
     * Check if there is one or more error.
     *
     * @return bool
     */
    protected function hasErrors()
    {
        return (bool) $this->errors;
    }

    /**
     * Check request authentication.
     *
     * @return bool
     */
    protected function authenticate()
    {
        if ($this->hasErrors()) {
            return false;
        }

        if (null === $this->_key) {
            $this->setError(401, 'Please enter the authentication key as the login. No password required', 16);
        } else {
            if (empty($this->_key)) {
                $this->setError(401, 'Authentication key is empty', 17);
            } elseif (strlen($this->_key) != '32') {
                $this->setError(401, 'Invalid authentication key format', 18);
            } else {
                if (WebserviceKey::isKeyActive($this->_key)) {
                    $this->keyPermissions = WebserviceKey::getPermissionForAccount($this->_key);
                } else {
                    $this->setError(401, 'Authentification key is not active', 20);
                }

                if (!$this->keyPermissions) {
                    $this->setError(401, 'No permission for this authentication key', 21);
                }
            }
        }
        if ($this->hasErrors()) {
            header('WWW-Authenticate: Basic realm="Welcome to PrestaShop Webservice, please enter the authentication key as the login. No password required."');
            $this->objOutput->setStatus(401);

            return false;
        } else {
            // only now we can say the access is authenticated
            $this->_authenticated = true;

            return true;
        }
    }

    /**
     * Check webservice activation.
     *
     * @return bool
     */
    protected function isActivated()
    {
        if (!Configuration::get('PS_WEBSERVICE')) {
            $this->setError(503, 'The PrestaShop webservice is disabled. Please activate it in the PrestaShop Back Office', 22);

            return false;
        }

        return true;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected function shopHasRight($key)
    {
        $sql = 'SELECT 1
				FROM ' . _DB_PREFIX_ . 'webservice_account wsa LEFT JOIN ' . _DB_PREFIX_ . 'webservice_account_shop wsas ON (wsa.id_webservice_account = wsas.id_webservice_account)
				WHERE wsa.key = \'' . pSQL($key) . '\'';
        $OR = [];
        foreach (self::$shopIDs as $id_shop) {
            $OR[] = ' wsas.id_shop = ' . (int) $id_shop . ' ';
        }
        $sql .= ' AND (' . implode('OR', $OR) . ') ';
        if (!Db::getInstance()->getValue($sql)) {
            $this->setError(403, 'No permission for this key on this shop', 132);

            return false;
        }

        return true;
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    protected function shopExists($params)
    {
        if (is_countable(self::$shopIDs) && count(self::$shopIDs)) {
            return true;
        }

        if (isset($params['id_shop'])) {
            if ($params['id_shop'] != 'all' && is_numeric($params['id_shop'])) {
                Shop::setContext(Shop::CONTEXT_SHOP, (int) $params['id_shop']);
                self::$shopIDs[] = (int) $params['id_shop'];

                return true;
            } elseif ($params['id_shop'] == 'all') {
                Shop::setContext(Shop::CONTEXT_ALL);
                self::$shopIDs = Shop::getShops(true, null, true);

                return true;
            }
        } else {
            self::$shopIDs[] = Context::getContext()->shop->id;

            return true;
        }
        $this->setError(404, 'This shop id does not exist', 999);

        return false;
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    protected function groupShopExists($params)
    {
        $idShopGroup = null;
        if (isset($params['id_shop_group']) && is_numeric($params['id_shop_group'])) {
            $idShopGroup = (int) $params['id_shop_group'];
        } elseif (isset($params['id_group_shop']) && is_numeric($params['id_group_shop'])) {
            $idShopGroup = (int) $params['id_group_shop'];
        }

        if (null !== $idShopGroup) {
            Shop::setContext(Shop::CONTEXT_GROUP, $idShopGroup);
            self::$shopIDs = Shop::getShops(true, $idShopGroup, true);
            if (!is_countable(self::$shopIDs) || count(self::$shopIDs) == 0) {
                // @FIXME Set ErrorCode !
                $this->setError(500, 'This shop group doesn\'t have shops', 999);

                return false;
            }
        }

        // id_shop_group isn't mandatory
        return true;
    }

    /**
     * Check HTTP method.
     *
     * @return bool
     */
    protected function checkHTTPMethod()
    {
        if (!in_array($this->method, ['GET', 'PUT', 'POST', 'PATCH', 'DELETE', 'HEAD'])) {
            $this->setError(405, 'Method ' . $this->method . ' is not valid', 23);
        } elseif (isset($this->urlSegment[0], $this->resourceList[$this->urlSegment[0]]['forbidden_method']) && in_array($this->method, $this->resourceList[$this->urlSegment[0]]['forbidden_method'])) {
            $this->setError(405, 'Method ' . $this->method . ' is not allowed for the resource ' . $this->urlSegment[0], 101);
        } elseif ($this->urlSegment[0] && !in_array($this->method, $this->keyPermissions[$this->urlSegment[0]])) {
            $this->setError(405, 'Method ' . $this->method . ' is not allowed for the resource ' . $this->urlSegment[0] . ' with this authentication key', 25);
        } else {
            return true;
        }

        return false;
    }

    /**
     * Check resource validity.
     *
     * @return bool
     */
    protected function checkResource()
    {
        $this->resourceList = $this->getResources();
        $resourceNames = array_keys($this->resourceList);
        if ($this->urlSegment[0] == '') {
            $this->resourceConfiguration['objectsNodeName'] = 'resources';
        } elseif (in_array($this->urlSegment[0], $resourceNames)) {
            if (!in_array($this->urlSegment[0], array_keys($this->keyPermissions))) {
                $this->setError(401, 'Resource of type "' . $this->urlSegment[0] . '" is not allowed with this authentication key', 26);

                return false;
            }
        } else {
            $this->setErrorDidYouMean(400, 'Resource of type "' . $this->urlSegment[0] . '" does not exists', $this->urlSegment[0], $resourceNames, 27);

            return false;
        }

        return true;
    }

    protected function setObjects()
    {
        $objects = [];
        $arr_avoid_id = [];
        $ids = [];
        if (isset($this->urlFragments['id'])) {
            preg_match('#^\[(.*)\]$#Ui', $this->urlFragments['id'], $matches);
            if (count($matches) > 1) {
                $ids = explode(',', $matches[1]);
            }
        } else {
            $ids[] = (int) $this->urlSegment[1];
        }
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $object = new $this->resourceConfiguration['retrieveData']['className']((int) $id);
                if (!$object->id) {
                    $arr_avoid_id[] = $id;
                } else {
                    $objects[] = $object;
                }
            }
        }

        if (!empty($arr_avoid_id) || empty($ids)) {
            $this->setError(404, 'Id(s) not exists: ' . implode(', ', $arr_avoid_id), 87);
            $this->_outputEnabled = true;
        }
    }

    protected function parseDisplayFields($str)
    {
        $bracket_level = 0;
        $part = [];
        $tmp = '';
        $str_len = strlen($str);
        for ($i = 0; $i < $str_len; ++$i) {
            if ($str[$i] == ',' && $bracket_level == 0) {
                $part[] = $tmp;
                $tmp = '';
            } else {
                $tmp .= $str[$i];
            }
            if ($str[$i] == '[') {
                ++$bracket_level;
            }
            if ($str[$i] == ']') {
                --$bracket_level;
            }
        }
        if ($tmp != '') {
            $part[] = $tmp;
        }
        $fields = [];
        foreach ($part as $str) {
            $field_name = trim(substr($str, 0, strpos($str, '[') === false ? strlen($str) : strpos($str, '[')));
            if (!isset($fields[$field_name])) {
                $fields[$field_name] = null;
            }
            if (strpos($str, '[') !== false) {
                $sub_fields = substr($str, strpos($str, '[') + 1, strlen($str) - strpos($str, '[') - 2);
                $tmp_array = [];
                if (strpos($sub_fields, ',') !== false) {
                    $tmp_array = explode(',', $sub_fields);
                } else {
                    $tmp_array = [$sub_fields];
                }
                $fields[$field_name] = (is_array($fields[$field_name])) ? array_merge($fields[$field_name], $tmp_array) : $tmp_array;
            }
        }

        return $fields;
    }

    public function setFieldsToDisplay()
    {
        // set the fields to display in the list : "full", "minimum", "field_1", "field_1,field_2,field_3"
        if (isset($this->urlFragments['display'])) {
            $this->fieldsToDisplay = $this->urlFragments['display'];
            if ($this->fieldsToDisplay != 'full' && $this->fieldsToDisplay != 'minimum') {
                preg_match('#^\[(.*)\]$#Ui', $this->fieldsToDisplay, $matches);
                if (count($matches)) {
                    $error = false;
                    $fieldsToTest = $this->parseDisplayFields($matches[1]);
                    foreach ($fieldsToTest as $field_name => $part) {
                        // in case it is not an association
                        if (!is_array($part)) {
                            // We have to allow new specific field for price calculation too
                            $error = (!isset($this->resourceConfiguration['fields'][$field_name]) && !isset($this->urlFragments['price'][$field_name]));
                        } else {
                            // if this association does not exists
                            if (!array_key_exists($field_name, $this->resourceConfiguration['associations'])) {
                                $error = true;
                            }

                            foreach ($part as $field) {
                                if ($field != 'id' && !array_key_exists($field, $this->resourceConfiguration['associations'][$field_name]['fields'])) {
                                    $error = true;

                                    break;
                                }
                            }
                        }
                        if ($error) {
                            $this->setError(400, 'Unable to display this field "' . $field_name . (is_array($part) ? ' (details : ' . var_export($part, true) . ')' : '') . '". However, these are available: ' . implode(', ', array_keys($this->resourceConfiguration['fields'])), 35);

                            return false;
                        }
                    }
                    $this->fieldsToDisplay = $fieldsToTest;
                } else {
                    $this->setError(400, 'The \'display\' syntax is wrong. You can set \'full\' or \'[field_1,field_2,field_3,...]\'. These are available: ' . implode(', ', array_keys($this->resourceConfiguration['fields'])), 36);

                    return false;
                }
            }
        }

        return true;
    }

    protected function manageFilters()
    {
        // filtered fields which can not use filters : hidden_fields
        $available_filters = [];
        // filtered i18n fields which can use filters
        $i18n_available_filters = [];
        foreach ($this->resourceConfiguration['fields'] as $fieldName => $field) {
            if (!isset($this->resourceConfiguration['hidden_fields'])
                || (!in_array($fieldName, $this->resourceConfiguration['hidden_fields']))) {
                if (!isset($field['i18n']) || (isset($field['i18n']) && !$field['i18n'])) {
                    $available_filters[] = $fieldName;
                } else {
                    $i18n_available_filters[] = $fieldName;
                }
            }
        }

        // Date feature : date=1
        if (!empty($this->urlFragments['date'])) {
            if (!in_array('date_add', $available_filters)) {
                $available_filters[] = 'date_add';
            }
            if (!in_array('date_upd', $available_filters)) {
                $available_filters[] = 'date_upd';
            }
            if (!array_key_exists('date_add', $this->resourceConfiguration['fields'])) {
                $this->resourceConfiguration['fields']['date_add'] = ['sqlId' => 'date_add'];
            }
            if (!array_key_exists('date_upd', $this->resourceConfiguration['fields'])) {
                $this->resourceConfiguration['fields']['date_upd'] = ['sqlId' => 'date_upd'];
            }
        } else {
            foreach ($available_filters as $key => $value) {
                if ($value == 'date_add' || $value == 'date_upd') {
                    unset($available_filters[$key]);
                }
            }
        }

        // construct SQL filter
        $sql_filter = '';
        $sql_join = '';
        if ($this->urlFragments) {
            $schema = 'schema';
            // if we have to display the schema
            if (isset($this->urlFragments[$schema])) {
                if ($this->urlFragments[$schema] == 'blank' || $this->urlFragments[$schema] == 'synopsis') {
                    $this->schemaToDisplay = $this->urlFragments[$schema];

                    return true;
                } else {
                    $this->setError(400, 'Please select a schema of type \'synopsis\' to get the whole schema informations (which fields are required, which kind of content...) or \'blank\' to get an empty schema to fill before using POST request', 28);

                    return false;
                }
            } else {
                // if there are filters
                if (isset($this->urlFragments['filter'])) {
                    foreach ($this->urlFragments['filter'] as $field => $url_param) {
                        if ($field != 'sort' && $field != 'limit') {
                            if (!in_array($field, $available_filters)) {
                                // if there are linked tables
                                if (isset($this->resourceConfiguration['linked_tables'][$field])) {
                                    // contruct SQL join for linked tables
                                    $sql_join .= 'LEFT JOIN `' . bqSQL(_DB_PREFIX_ . $this->resourceConfiguration['linked_tables'][$field]['table']) . '` `' . bqSQL($field) . '` ON (main.`' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '` = `' . bqSQL($field) . '`.`' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '`)' . "\n";

                                    // construct SQL filter for linked tables
                                    foreach ($url_param as $field2 => $value) {
                                        if (isset($this->resourceConfiguration['linked_tables'][$field]['fields'][$field2])) {
                                            $linked_field = $this->resourceConfiguration['linked_tables'][$field]['fields'][$field2];
                                            $sql_filter .= $this->getSQLRetrieveFilter($linked_field['sqlId'], $value, $field . '.');
                                        } else {
                                            $list = array_keys($this->resourceConfiguration['linked_tables'][$field]['fields']);
                                            $this->setErrorDidYouMean(400, 'This filter does not exist for this linked table', $field2, $list, 29);

                                            return false;
                                        }
                                    }
                                } elseif ($url_param != '' && in_array($field, $i18n_available_filters)) {
                                    if (!is_array($url_param)) {
                                        $url_param = [$url_param];
                                    }
                                    $sql_join .= 'LEFT JOIN `' . bqSQL(_DB_PREFIX_ . $this->resourceConfiguration['retrieveData']['table']) . '_lang` AS main_i18n ON (main.`' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '` = main_i18n.`' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '`)' . "\n";
                                    foreach ($url_param as $field2 => $value) {
                                        $linked_field = $this->resourceConfiguration['fields'][$field];
                                        $sql_filter .= $this->getSQLRetrieveFilter($linked_field['sqlId'], $value, 'main_i18n.');
                                        $language_filter = '[' . implode('|', $this->_available_languages) . ']';
                                        $sql_filter .= $this->getSQLRetrieveFilter('id_lang', $language_filter, 'main_i18n.');
                                    }
                                } elseif (is_array($url_param)) {
                                    // if there are filters on linked tables but there are no linked table
                                    if (isset($this->resourceConfiguration['linked_tables'])) {
                                        $this->setErrorDidYouMean(400, 'This linked table does not exist', $field, array_keys($this->resourceConfiguration['linked_tables']), 30);
                                    } else {
                                        $this->setError(400, 'There is no existing linked table for this resource', 31);
                                    }

                                    return false;
                                } else {
                                    $this->setErrorDidYouMean(400, 'This filter does not exist', $field, $available_filters, 32);

                                    return false;
                                }
                            } elseif ($url_param == '') {
                                $this->setError(400, 'The filter "' . $field . '" is malformed.', 33);

                                return false;
                            } else {
                                if (isset($this->resourceConfiguration['fields'][$field]['getter'])) {
                                    $this->setError(400, 'The field "' . $field . '" is dynamic. It is not possible to filter GET query with this field.', 34);

                                    return false;
                                } else {
                                    if (isset($this->resourceConfiguration['retrieveData']['tableAlias'])) {
                                        $sql_filter .= $this->getSQLRetrieveFilter($this->resourceConfiguration['fields'][$field]['sqlId'], $url_param, $this->resourceConfiguration['retrieveData']['tableAlias'] . '.');
                                    } else {
                                        $sql_filter .= $this->getSQLRetrieveFilter($this->resourceConfiguration['fields'][$field]['sqlId'], $url_param);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!$this->setFieldsToDisplay()) {
            return false;
        }
        // construct SQL Sort
        $sql_sort = '';
        if (isset($this->urlFragments['sort'])) {
            preg_match('#^\[(.*)\]$#Ui', $this->urlFragments['sort'], $matches);
            if (count($matches) > 1) {
                $sorts = explode(',', $matches[1]);
            } else {
                $sorts = [$this->urlFragments['sort']];
            }

            $sql_sort .= ' ORDER BY ';

            foreach ($sorts as $sort) {
                $delimiterPosition = strrpos($sort, '_');
                $fieldName = $direction = '';
                if ($delimiterPosition !== false) {
                    $fieldName = substr($sort, 0, $delimiterPosition);
                    $direction = strtoupper(substr($sort, $delimiterPosition + 1));
                }
                if ($delimiterPosition === false || !in_array($direction, ['ASC', 'DESC'])) {
                    $this->setError(400, 'The "sort" value has to be formed as this example: "field_ASC" or \'[field_1_DESC,field_2_ASC,field_3_ASC,...]\' ("field" has to be an available field)', 37);

                    return false;
                } elseif (!in_array($fieldName, $available_filters) && !in_array($fieldName, $i18n_available_filters)) {
                    $this->setError(400, 'Unable to filter by this field. However, these are available: ' . implode(', ', $available_filters) . ', for i18n fields:' . implode(', ', $i18n_available_filters), 38);

                    return false;
                } elseif (in_array($fieldName, $i18n_available_filters)) {
                    // for sort on i18n field
                    if (!preg_match('#main_i18n#', $sql_join)) {
                        $sql_join .= 'LEFT JOIN `' . _DB_PREFIX_ . bqSQL($this->resourceConfiguration['retrieveData']['table']) . '_lang` AS main_i18n ON (main.`' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '` = main_i18n.`' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '`)' . "\n";
                    }
                    $sql_sort .= 'main_i18n.`' . bqSQL($this->resourceConfiguration['fields'][$fieldName]['sqlId']) . '` ' . $direction . ', '; // ORDER BY main_i18n.`field` ASC|DESC
                } else {
                    /** @var ObjectModel $object */
                    $object = new $this->resourceConfiguration['retrieveData']['className']();
                    $assoc = Shop::getAssoTable($this->resourceConfiguration['retrieveData']['table']);
                    if ($assoc !== false && $assoc['type'] == 'shop' && ($object->isMultiShopField($this->resourceConfiguration['fields'][$fieldName]['sqlId']) || $fieldName == 'id')) {
                        $table_alias = 'multi_shop_' . $this->resourceConfiguration['retrieveData']['table'];
                    } else {
                        $table_alias = '';
                    }
                    $sql_sort .= (isset($this->resourceConfiguration['retrieveData']['tableAlias']) ? '`' . bqSQL($this->resourceConfiguration['retrieveData']['tableAlias']) . '`.' : '`' . bqSQL($table_alias) . '`.') . '`' . pSQL($this->resourceConfiguration['fields'][$fieldName]['sqlId']) . '` ' . $direction . ', '; // ORDER BY `field` ASC|DESC
                }
            }
            $sql_sort = rtrim($sql_sort, ', ') . "\n";
        }

        // construct SQL Limit
        $sql_limit = '';
        if (isset($this->urlFragments['limit'])) {
            $limitArgs = explode(',', $this->urlFragments['limit']);
            if (count($limitArgs) > 2) {
                $this->setError(400, 'The "limit" value has to be formed as this example: "5,25" or "10"', 39);

                return false;
            } else {
                $sql_limit .= ' LIMIT ' . (int) $limitArgs[0] . (isset($limitArgs[1]) ? ', ' . (int) ($limitArgs[1]) : '') . "\n"; // LIMIT X|X, Y
            }
        }
        $filters['sql_join'] = $sql_join;
        $filters['sql_filter'] = $sql_filter;
        $filters['sql_sort'] = $sql_sort;
        $filters['sql_limit'] = $sql_limit;

        return $filters;
    }

    public function getFilteredObjectList()
    {
        $objects = [];
        $filters = $this->manageFilters();

        /* If we only need to display the synopsis, analyzing the first row is sufficient */
        if (isset($this->urlFragments['schema']) && in_array($this->urlFragments['schema'], ['blank', 'synopsis'])) {
            $filters = ['sql_join' => '', 'sql_filter' => '', 'sql_sort' => '', 'sql_limit' => ' LIMIT 1'];
        }

        $this->resourceConfiguration['retrieveData']['params'][] = $filters['sql_join'];
        $this->resourceConfiguration['retrieveData']['params'][] = $filters['sql_filter'];
        $this->resourceConfiguration['retrieveData']['params'][] = $filters['sql_sort'];
        $this->resourceConfiguration['retrieveData']['params'][] = $filters['sql_limit'];
        // list entities

        $tmp = new $this->resourceConfiguration['retrieveData']['className']();
        $sqlObjects = call_user_func_array([$tmp, $this->resourceConfiguration['retrieveData']['retrieveMethod']], $this->resourceConfiguration['retrieveData']['params']);
        if ($sqlObjects) {
            foreach ($sqlObjects as $sqlObject) {
                if ($this->fieldsToDisplay == 'minimum') {
                    $obj = new $this->resourceConfiguration['retrieveData']['className']();
                    $obj->id = (int) $sqlObject[$this->resourceConfiguration['fields']['id']['sqlId']];
                    $objects[] = $obj;
                } else {
                    $objects[] = new $this->resourceConfiguration['retrieveData']['className']((int) $sqlObject[$this->resourceConfiguration['fields']['id']['sqlId']]);
                }
            }

            return $objects;
        }
    }

    public function getFilteredObjectDetails()
    {
        if (!$this->setFieldsToDisplay()) {
            return false;
        }

        $objects = [];
        if (!isset($this->urlFragments['display'])) {
            $this->fieldsToDisplay = 'full';
        }

        // get entity details
        $object = new $this->resourceConfiguration['retrieveData']['className']((int) $this->urlSegment[1]);
        if ($object->id) {
            $objects[] = $object;
            // Check if Object is accessible for this/those id_shop
            $assoc = Shop::getAssoTable($this->resourceConfiguration['retrieveData']['table']);
            if ($assoc !== false) {
                $check_shop_group = false;

                $sql = 'SELECT 1
	 						FROM `' . bqSQL(_DB_PREFIX_ . $this->resourceConfiguration['retrieveData']['table']);
                if ($assoc['type'] != 'fk_shop') {
                    $sql .= '_' . $assoc['type'];
                } else {
                    $def = ObjectModel::getDefinition($this->resourceConfiguration['retrieveData']['className']);
                    if (isset($def['fields']['id_shop_group'])) {
                        $check_shop_group = true;
                    }
                }
                $sql .= '`';

                $OR = [];
                foreach (self::$shopIDs as $id_shop) {
                    $OR[] = ' (id_shop = ' . (int) $id_shop . ($check_shop_group ? ' OR (id_shop = 0 AND id_shop_group=' . (int) Shop::getGroupFromShop((int) $id_shop) . ')' : '') . ') ';
                }

                $check = ' WHERE (' . implode('OR', $OR) . ') AND `' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '` = ' . (int) $this->urlSegment[1];
                if (!Db::getInstance()->getValue($sql . $check)) {
                    $this->setError(404, 'This ' . $this->resourceConfiguration['retrieveData']['className'] . ' (' . (int) $this->urlSegment[1] . ') does not exists on this shop', 131);
                }
            }

            return $objects;
        }
        if (!count($this->errors)) {
            $this->objOutput->setStatus(404);
            $this->_outputEnabled = false;

            return false;
        }
    }

    /**
     * Execute GET and HEAD requests.
     *
     * Build filter
     * Build fields display
     * Build sort
     * Build limit
     *
     * @return bool
     */
    public function executeEntityGetAndHead()
    {
        if ($this->resourceConfiguration['objectsNodeName'] != 'resources') {
            if (!isset($this->urlSegment[1]) || !strlen($this->urlSegment[1])) {
                $return = $this->getFilteredObjectList();
            } else {
                $return = $this->getFilteredObjectDetails();
            }

            if (!$return) {
                return false;
            } else {
                $this->_outputEnabled = true;
                $this->objects = $return;
            }
        }

        return true;
    }

    /**
     * Execute POST method on a PrestaShop entity.
     *
     * @return bool
     */
    public function executeEntityPost()
    {
        return $this->saveEntityFromXml(201);
    }

    /**
     * Execute PUT method on a PrestaShop entity.
     *
     * @return bool
     */
    public function executeEntityPut()
    {
        return $this->saveEntityFromXml(200);
    }

    /**
     * Execute PATCH method on a PrestaShop entity.
     *
     * @return bool
     */
    public function executeEntityPatch(): bool
    {
        return $this->saveEntityFromXml(200);
    }

    /**
     * Execute DELETE method on a PrestaShop entity.
     *
     * @return void
     */
    public function executeEntityDelete()
    {
        $objects = [];
        $arr_avoid_id = [];
        $ids = [];
        if (isset($this->urlFragments['id'])) {
            preg_match('#^\[(.*)\]$#Ui', $this->urlFragments['id'], $matches);
            if (count($matches) > 1) {
                $ids = explode(',', $matches[1]);
            }
        } else {
            $ids[] = (int) $this->urlSegment[1];
        }
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $object = new $this->resourceConfiguration['retrieveData']['className']((int) $id);
                if (!$object->id) {
                    $arr_avoid_id[] = $id;
                } else {
                    $objects[] = $object;
                }
            }
        }

        $postponeNTreeRegeneration = false;

        if (!empty($arr_avoid_id) || empty($ids)) {
            $this->setError(404, 'Id(s) not exists: ' . implode(', ', $arr_avoid_id), 87);
            $this->_outputEnabled = true;
        } else {
            foreach ($objects as $object) {
                if ($object instanceof Category) {
                    $object->doNotRegenerateNTree = true;
                    $postponeNTreeRegeneration = true;
                }

                /* @var ObjectModel $object */
                if (isset($this->resourceConfiguration['objectMethods']['delete'])) {
                    $result = $object->{$this->resourceConfiguration['objectMethods']['delete']}();
                } else {
                    $result = $object->delete();
                }

                if (!$result) {
                    $arr_avoid_id[] = $object->id;
                }
            }
            if (!empty($arr_avoid_id)) {
                $this->setError(500, 'Id(s) wasn\'t deleted: ' . implode(', ', $arr_avoid_id), 88);
                $this->_outputEnabled = true;
            } else {
                $this->_outputEnabled = false;
            }
        }

        if ($postponeNTreeRegeneration) {
            Category::regenerateEntireNtree();
        }
    }

    /**
     * save Entity Object from XML.
     *
     * @param int $successReturnCode
     *
     * @return bool
     */
    protected function saveEntityFromXml($successReturnCode)
    {
        try {
            $xml = new SimpleXMLElement($this->_inputXml);
        } catch (Exception $error) {
            $this->setError(500, 'XML error : ' . $error->getMessage() . "\n" . 'XML length : ' . strlen($this->_inputXml) . "\n" . 'Original XML : ' . $this->_inputXml, 127);

            return false;
        }

        $xmlEntities = $xml->children();
        $object = null;

        $ids = [];
        foreach ($xmlEntities as $entity) {
            // To cast in string allow to check null values
            if ((string) $entity->id != '') {
                $ids[] = (int) $entity->id;
            }
        }
        if ($this->method == 'PUT' || $this->method == 'PATCH') {
            $ids2 = array_unique($ids);
            if (count($ids2) != count($ids)) {
                $this->setError(400, 'id is duplicate in request', 89);

                return false;
            }
            if (count($xmlEntities) != count($ids)) {
                $this->setError(400, 'id is required when modifying a resource', 90);

                return false;
            }
        } elseif ($this->method == 'POST' && count($ids) > 0) {
            $this->setError(400, 'id is forbidden when adding a new resource', 91);

            return false;
        }

        $postponeNTreeRegeneration = false;
        foreach ($xmlEntities as $xmlEntity) {
            /** @var SimpleXMLElement $xmlEntity */
            $attributes = $xmlEntity->children();

            /* @var ObjectModel $object */
            if ($this->method == 'POST') {
                $object = new $this->resourceConfiguration['retrieveData']['className']();
            } elseif ($this->method == 'PUT' || $this->method == 'PATCH') {
                $object = new $this->resourceConfiguration['retrieveData']['className']((int) $attributes->id);
                if (!$object->id) {
                    $this->setError(404, 'Invalid ID', 92);

                    return false;
                }
            }

            if ($object instanceof Category) {
                $object->doNotRegenerateNTree = true;
                $postponeNTreeRegeneration = true;
            }

            $this->objects[] = $object;
            $i18n = false;
            // attributes
            foreach ($this->resourceConfiguration['fields'] as $fieldName => $fieldProperties) {
                // only process fields actually in the input XML
                if ($this->method == 'PATCH' && !isset($attributes->$fieldName)) {
                    continue;
                }
                $sqlId = $fieldProperties['sqlId'];
                if ($fieldName == 'id') {
                    $sqlId = $fieldName;
                }
                if (isset($attributes->$fieldName, $fieldProperties['sqlId']) && (!isset($fieldProperties['i18n']) || !$fieldProperties['i18n'])) {
                    if (isset($fieldProperties['setter'])) {
                        // if we have to use a specific setter
                        if (!$fieldProperties['setter']) {
                            // if it's forbidden to set this field
                            $this->setError(400, 'parameter "' . $fieldName . '" not writable. Please remove this attribute of this XML', 93);

                            return false;
                        } else {
                            $object->{$fieldProperties['setter']}((string) $attributes->$fieldName);
                        }
                    } elseif (property_exists($object, $sqlId)) {
                        $object->$sqlId = (string) $attributes->$fieldName;
                    } else {
                        $this->setError(400, 'Parameter "' . $fieldName . '" can\'t be set to the object "' . $this->resourceConfiguration['retrieveData']['className'] . '"', 123);
                    }
                } elseif (isset($fieldProperties['required']) && $fieldProperties['required'] && !$fieldProperties['i18n']) {
                    $this->setError(400, 'parameter "' . $fieldName . '" required', 41);

                    return false;
                } elseif ((!isset($fieldProperties['required']) || !$fieldProperties['required']) && property_exists($object, $sqlId)) {
                    $object->$sqlId = null;
                }
                if (isset($fieldProperties['i18n']) && $fieldProperties['i18n']) {
                    $i18n = true;
                    if (isset($attributes->$fieldName, $attributes->$fieldName->language)) {
                        foreach ($attributes->$fieldName->language as $lang) {
                            /* @var SimpleXMLElement $lang */
                            $object->{$fieldName}[(int) $lang->attributes()->id] = (string) $lang;
                        }
                    } else {
                        $object->{$fieldName} = (string) $attributes->$fieldName;
                    }
                }
            }

            // Apply the modifiers if they exist
            foreach ($this->resourceConfiguration['fields'] as $fieldName => $fieldProperties) {
                if (isset($fieldProperties['modifier']['modifier']) && $fieldProperties['modifier']['http_method'] & constant('WebserviceRequest::HTTP_' . $this->method)) {
                    $object->{$fieldProperties['modifier']['modifier']}();
                }
            }

            if (!$this->hasErrors()) {
                if ($i18n && ($retValidateFieldsLang = $object->validateFieldsLang(false, true)) !== true) {
                    $this->setError(400, 'Validation error: "' . $retValidateFieldsLang . '"', 84);

                    return false;
                } elseif (($retValidateFields = $object->validateFields(false, true)) !== true) {
                    $this->setError(400, 'Validation error: "' . $retValidateFields . '"', 85);

                    return false;
                } else {
                    // Call alternative method for add/update
                    $objectMethod = ($this->method == 'POST' ? 'add' : 'update');
                    if (isset($this->resourceConfiguration['objectMethods']) && array_key_exists($objectMethod, $this->resourceConfiguration['objectMethods'])) {
                        $objectMethod = $this->resourceConfiguration['objectMethods'][$objectMethod];
                    }
                    $result = $object->{$objectMethod}();
                    if ($result) {
                        if (isset($attributes->associations)) {
                            foreach ($attributes->associations->children() as $association) {
                                /** @var SimpleXMLElement $association */
                                // associations
                                if (isset($this->resourceConfiguration['associations'][$association->getName()])) {
                                    $assocItems = $association->children();
                                    $values = [];
                                    foreach ($assocItems as $assocItem) {
                                        /** @var SimpleXMLElement $assocItem */
                                        $fields = $assocItem->children();
                                        $entry = [];
                                        foreach ($fields as $fieldName => $fieldValue) {
                                            $entry[$fieldName] = (string) $fieldValue;
                                        }
                                        $values[] = $entry;
                                    }
                                    $setter = $this->resourceConfiguration['associations'][$association->getName()]['setter'];
                                    if (null !== $setter && $setter && method_exists($object, $setter) && !$object->$setter($values)) {
                                        $this->setError(500, 'Error occurred while setting the ' . $association->getName() . ' value', 85);

                                        return false;
                                    }
                                } elseif ($association->getName() != 'i18n') {
                                    $this->setError(400, 'The association "' . $association->getName() . '" does not exists', 86);

                                    return false;
                                }
                            }
                        }
                        $assoc = Shop::getAssoTable($this->resourceConfiguration['retrieveData']['table']);
                        if ($assoc !== false && $assoc['type'] != 'fk_shop') {
                            // PUT nor POST is destructive, no deletion
                            $sql = 'INSERT IGNORE INTO `' . bqSQL(_DB_PREFIX_ . $this->resourceConfiguration['retrieveData']['table'] . '_' . $assoc['type']) . '` (id_shop, `' . bqSQL($this->resourceConfiguration['fields']['id']['sqlId']) . '`) VALUES ';
                            foreach (self::$shopIDs as $id) {
                                $sql .= '(' . (int) $id . ',' . (int) $object->id . ')';
                                if ($id != end(self::$shopIDs)) {
                                    $sql .= ', ';
                                }
                            }
                            Db::getInstance()->execute($sql);
                        }
                    } else {
                        $this->setError(500, 'Unable to save resource', 46);
                    }
                }
            }
        }

        if ($postponeNTreeRegeneration) {
            Category::regenerateEntireNtree();
        }

        if (!$this->hasErrors()) {
            $this->objOutput->setStatus($successReturnCode);

            return true;
        }

        return false;
    }

    /**
     * @param string $sqlId
     * @param string $filterValue
     * @param string $tableAlias default value is 'main.'
     *
     * @return string
     */
    protected function getSQLRetrieveFilter($sqlId, $filterValue, $tableAlias = 'main.')
    {
        return SQLUtils::getSQLRetrieveFilter($sqlId, $filterValue, $tableAlias);
    }

    public function filterLanguage()
    {
        $arr_languages = [];
        $length_values = strlen($this->urlFragments['language']);
        // if just one language is asked
        if (is_numeric($this->urlFragments['language'])) {
            $arr_languages[] = (int) $this->urlFragments['language'];
        } elseif (strpos($this->urlFragments['language'], '[') === 0
            // if a range or a list is asked
            && strpos($this->urlFragments['language'], ']') === $length_values - 1) {
            if (strpos($this->urlFragments['language'], '|') !== false
                xor strpos($this->urlFragments['language'], ',') !== false) {
                $params_values = str_replace([']', '['], '', $this->urlFragments['language']);
                // it's a list
                if (strpos($params_values, '|') !== false) {
                    $list_enabled_lang = explode('|', $params_values);
                    $arr_languages = $list_enabled_lang;
                } elseif (strpos($params_values, ',') !== false) {
                    // it's a range
                    $range_enabled_lang = explode(',', $params_values);
                    if (count($range_enabled_lang) != 2) {
                        $this->setError(400, 'A range value for a language must contains only 2 values', 78);

                        return false;
                    }
                    for ($i = $range_enabled_lang[0]; $i <= $range_enabled_lang[1]; ++$i) {
                        $arr_languages[] = $i;
                    }
                }
            } elseif (preg_match('#\[(\d)+\]#Ui', $this->urlFragments['language'], $match_lang)) {
                $arr_languages[] = $match_lang[1];
            }
        } else {
            $this->setError(400, 'language value is wrong', 79);

            return false;
        }

        $result = array_map('is_numeric', $arr_languages);
        if (array_search(false, $result, true)) {
            $this->setError(400, 'Language ID must be numeric', 80);

            return false;
        }

        foreach ($arr_languages as $key => $id_lang) {
            if (!Language::getLanguage($id_lang)) {
                unset($arr_languages[$key]);
            }
        }

        return $arr_languages;
    }

    /**
     * Thanks to the (WebserviceOutputBuilder) WebserviceKey::objOutput
     * Method build the output depend on the WebserviceRequest::outputFormat
     * and set HTTP header parameters.
     *
     * @return array with displaying informations (used in the dispatcher)
     */
    protected function returnOutput()
    {
        $return = [];

        // Write headers
        $this->objOutput
            ->setHeaderParams('Access-Time', (string) time())
            ->setHeaderParams('X-Powered-By', 'PrestaShop Webservice')
            ->setHeaderParams('Execution-Time', (string) round(microtime(true) - $this->_startTime, 3))
        ;

        $return['type'] = strtolower($this->outputFormat);

        // write this header only now (avoid hackers happiness...)
        if ($this->_authenticated) {
            $this->objOutput->setHeaderParams('PSWS-Version', _PS_VERSION_);
        }

        // If Specific Management is asked
        if ($this->objectSpecificManagement instanceof WebserviceSpecificManagementInterface) {
            try {
                $return['content'] = $this->objectSpecificManagement->getContent();
            } catch (WebserviceException $e) {
                if ($e->getType() == WebserviceException::DID_YOU_MEAN) {
                    $this->setErrorDidYouMean($e->getStatus(), $e->getMessage(), $e->getWrongValue(), $e->getAvailableValues(), $e->getCode());
                } elseif ($e->getType() == WebserviceException::SIMPLE) {
                    $this->setError($e->getStatus(), $e->getMessage(), $e->getCode());
                }
            }
        }

        // for use a general output
        if (!$this->hasErrors() && $this->objectSpecificManagement == null) {
            if (empty($this->objects)) {
                try {
                    $return['content'] = $this->objOutput->getResourcesList($this->keyPermissions);
                } catch (WebserviceException $e) {
                    if ($e->getType() == WebserviceException::DID_YOU_MEAN) {
                        $this->setErrorDidYouMean($e->getStatus(), $e->getMessage(), $e->getWrongValue(), $e->getAvailableValues(), $e->getCode());
                    } elseif ($e->getType() == WebserviceException::SIMPLE) {
                        $this->setError($e->getStatus(), $e->getMessage(), $e->getCode());
                    }
                }
            } else {
                try {
                    if (isset($this->urlSegment[1]) && !empty($this->urlSegment[1])) {
                        $type_of_view = WebserviceOutputBuilder::VIEW_DETAILS;
                    } else {
                        $type_of_view = WebserviceOutputBuilder::VIEW_LIST;
                    }

                    if (in_array($this->method, ['PUT', 'POST', 'PATCH'])) {
                        $type_of_view = WebserviceOutputBuilder::VIEW_DETAILS;
                        $this->fieldsToDisplay = 'full';
                    }

                    $return['content'] = $this->objOutput->getContent($this->objects, $this->schemaToDisplay, $this->fieldsToDisplay, $this->depth, $type_of_view);
                } catch (WebserviceException $e) {
                    if ($e->getType() == WebserviceException::DID_YOU_MEAN) {
                        $this->setErrorDidYouMean($e->getStatus(), $e->getMessage(), $e->getWrongValue(), $e->getAvailableValues(), $e->getCode());
                    } elseif ($e->getType() == WebserviceException::SIMPLE) {
                        $this->setError($e->getStatus(), $e->getMessage(), $e->getCode());
                    }
                } catch (Exception $e) {
                    $this->setError(500, $e->getMessage(), $e->getCode());
                }
            }
        }

        // if the output is not enable, delete the content
        // the type content too
        if (!$this->_outputEnabled) {
            unset($return['type']);
            if (isset($return['content'])) {
                unset($return['content']);
            }
        } elseif (isset($return['content'])) {
            $return['content_sha1'] = sha1($return['content']);
            $this->objOutput->setHeaderParams('Content-Sha1', $return['content_sha1']);
        }

        // if errors happens when creating returned xml,
        // the usual xml content is replaced by the nice error handler content
        if ($this->hasErrors()) {
            $this->_outputEnabled = true;
            $return['content'] = $this->objOutput->getErrors($this->errors);
        }

        if (!isset($return['content']) || strlen($return['content']) <= 0) {
            $this->objOutput->setHeaderParams('Content-Type', '');
        }

        $return['headers'] = $this->objOutput->buildHeader();
        restore_error_handler();

        return $return;
    }

    /**
     * @return array
     */
    public static function getallheaders()
    {
        $retarr = [];

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            $headers = array_merge($_ENV, $_SERVER);
            foreach ($headers as $key => $val) {
                // we need this header
                if (strpos(strtolower($key), 'content-type') !== false) {
                    continue;
                }
                if (strtoupper(substr($key, 0, 5)) != 'HTTP_') {
                    unset($headers[$key]);
                }
            }
        }
        // Normalize this array to Cased-Like-This structure.
        foreach ($headers as $key => $value) {
            $key = preg_replace('/^HTTP_/i', '', $key);
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace(['-', '_'], ' ', $key))));
            $retarr[$key] = $value;
        }
        ksort($retarr);

        return $retarr;
    }

    /**
     * Set Object Specific Management
     *
     * @param mixed $objectSpecificManagement
     */
    public function setObjectSpecificManagement($objectSpecificManagement)
    {
        $this->objectSpecificManagement = $objectSpecificManagement;
    }
}
