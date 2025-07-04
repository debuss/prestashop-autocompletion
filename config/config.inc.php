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

use PrestaShop\PrestaShop\Core\Session\SessionHandler;
use tools\profiling\Tools;

// Custom defines made by users
if (is_file(__DIR__ . '/defines_custom.inc.php')) {
    include_once __DIR__ . '/defines_custom.inc.php';
}

require_once __DIR__ . '/defines.inc.php';

require_once _PS_CONFIG_DIR_ . 'autoload.php';

$start_time = microtime(true);

/* SSL configuration */
define('_PS_SSL_PORT_', 443);

/* Improve PHP configuration to prevent issues */
ini_set('default_charset', 'utf-8');

/* in dev mode - check if composer was executed */
if (is_dir(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'admin-dev') && (!is_dir(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'vendor') ||
        !file_exists(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php'))) {
    die('Config check Error : please install <a href="https://getcomposer.org/">composer</a>. Then run "php composer.phar install"');
}

/* No settings file? goto installer... */
if (!file_exists(_PS_ROOT_DIR_ . '/app/config/parameters.yml') && !file_exists(_PS_ROOT_DIR_ . '/app/config/parameters.php')) {
    Tools::redirectToInstall();
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';
// If this const is not defined others are likely to be absent but this one is the most likely to cause a fatal error,
// the following initialization is going to fail, so we throw an exception early
if (!defined('_DB_PREFIX_')) {
    throw new PrestaShopException('Constant _DB_PREFIX_ not defined');
}

if (defined('_PS_CREATION_DATE_')) {
    $creationDate = _PS_CREATION_DATE_;
    if (empty($creationDate)) {
        Tools::redirectToInstall();
    }
} else {
    Tools::redirectToInstall();
}

/* Custom config made by users */
if (is_file(_PS_CUSTOM_CONFIG_FILE_)) {
    include_once _PS_CUSTOM_CONFIG_FILE_;
}

if (_PS_DEBUG_PROFILING_) {
    include_once _PS_TOOL_DIR_ . 'profiling/Profiler.php';
    include_once _PS_TOOL_DIR_ . 'profiling/Controller.php';
    include_once _PS_TOOL_DIR_ . 'profiling/ObjectModel.php';
    include_once _PS_TOOL_DIR_ . 'profiling/Db.php';
    include_once _PS_TOOL_DIR_ . 'profiling/Hook.php';
    include_once _PS_TOOL_DIR_ . 'profiling/Module.php';
    include_once _PS_TOOL_DIR_ . 'profiling/Tools.php';
}

if (Tools::convertBytes(ini_get('upload_max_filesize')) < Tools::convertBytes('100M')) {
    ini_set('upload_max_filesize', '100M');
}

if (Tools::isPHPCLI() && isset($argc, $argv)) {
    Tools::argvToGET($argc, $argv);
}

/* Redefine REQUEST_URI if empty (on some webservers...) */
if (!isset($_SERVER['REQUEST_URI']) || empty($_SERVER['REQUEST_URI'])) {
    if (!isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['SCRIPT_FILENAME'])) {
        $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_FILENAME'];
    }
    if (isset($_SERVER['SCRIPT_NAME'])) {
        if (basename($_SERVER['SCRIPT_NAME']) == 'index.php' && empty($_SERVER['QUERY_STRING'])) {
            $_SERVER['REQUEST_URI'] = dirname($_SERVER['SCRIPT_NAME']) . '/';
        } else {
            $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
            if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
            }
        }
    }
}

/* Trying to redefine HTTP_HOST if empty (on some webservers...) */
if (!isset($_SERVER['HTTP_HOST']) || empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = @getenv('HTTP_HOST');
}

$context = Context::getContext();

/* Initialize the current Shop */
try {
    $context->shop = Shop::initialize();
} catch (PrestaShopException $e) {
    // In CLI command the Shop initialization is bound to fail when the shop is not installed, but we don't want to stop
    // the process or this will break any Symfony command even a simple ./bin/console)
    $e->displayMessage(!ToolsCore::isPHPCLI());
}

if ($context->shop) {
    define('__PS_BASE_URI__', $context->shop->getBaseURI());
} else {
    define('__PS_BASE_URI__', '/');
}

if ($context->shop && $context->shop->theme) {
    define('_THEME_NAME_', $context->shop->theme->getName());
    define('_PARENT_THEME_NAME_', $context->shop->theme->get('parent') ?: '');
} else {
    // Not ideal fallback but on install when nothing else is available it does the job, better than not having these const at all
    define('_THEME_NAME_', 'classic');
    define('_PARENT_THEME_NAME_', '');
}

/* Include all defines related to base uri and theme name */
require_once __DIR__ . '/defines_uri.inc.php';

global $_MODULES;
$_MODULES = array();

/* Load all languages */
Language::loadLanguages();

/* Loading default country */
$default_country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'), (int) Configuration::get('PS_LANG_DEFAULT'));
$context->country = $default_country;

/* It is not safe to rely on the system's timezone settings, and this would generate a PHP Strict Standards notice. */
@date_default_timezone_set(Configuration::get('PS_TIMEZONE'));

/* Set locales */
$locale = strtolower(Configuration::get('PS_LOCALE_LANGUAGE')) . '_' . strtoupper(Configuration::get('PS_LOCALE_COUNTRY'));
/* Please do not use LC_ALL here http://www.php.net/manual/fr/function.setlocale.php#25041 */
setlocale(LC_COLLATE, $locale . '.UTF-8', $locale . '.utf8');
setlocale(LC_CTYPE, $locale . '.UTF-8', $locale . '.utf8');
setlocale(LC_TIME, $locale . '.UTF-8', $locale . '.utf8');
setlocale(LC_NUMERIC, 'en_US.UTF-8', 'en_US.utf8');

/* Instantiate cookie */
$cookie_lifetime = defined('_PS_ADMIN_DIR_') ? (int) Configuration::get('PS_COOKIE_LIFETIME_BO') : (int) Configuration::get('PS_COOKIE_LIFETIME_FO');
if ($cookie_lifetime > 0) {
    $cookie_lifetime = time() + (max($cookie_lifetime, 1) * 3600);
}

$force_ssl = Configuration::get('PS_SSL_ENABLED');
if (defined('_PS_ADMIN_DIR_')) {
    $cookie = new Cookie('psAdmin', '', $cookie_lifetime, null, false, $force_ssl);
} else {
    $domains = null;
    if ($context->shop->getGroup()->share_order) {
        $cookie = new Cookie('ps-sg' . $context->shop->getGroup()->id, '', $cookie_lifetime, $context->shop->getUrlsSharedCart(), false, $force_ssl);
    } else {
        if ($context->shop->domain != $context->shop->domain_ssl) {
            $domains = array($context->shop->domain_ssl, $context->shop->domain);
        }

        $cookie = new Cookie('ps-s' . $context->shop->id, '', $cookie_lifetime, $domains, false, $force_ssl);
    }
}

if (PHP_SAPI !== 'cli') {
    $sessionHandler = new SessionHandler(
        $cookie_lifetime,
        $force_ssl,
        Configuration::get('PS_COOKIE_SAMESITE'),
        Context::getContext()->shop->physical_uri
    );
    $sessionHandler->init();

    $context->session = $sessionHandler->getSession();
}

$context->cookie = $cookie;

/* Create employee if in BO, customer else */
if (defined('_PS_ADMIN_DIR_')) {
    $employee = new Employee((int) $cookie->id_employee);
    $context->employee = $employee;

    /* Auth on shops are recached after employee assignation */
    if ($employee->id_profile != _PS_ADMIN_PROFILE_) {
        Shop::cacheShops(true);
    }

    $cookie->id_lang = (int) $employee->id_lang;
}

/* if the language stored in the cookie is not available language, use default language */
if (isset($cookie->id_lang) && $cookie->id_lang) {
    $language = new Language((int) $cookie->id_lang);
}

$isNotValidLanguage = !isset($language) || !Validate::isLoadedObject($language);
// `true` if language is defined from multishop or backoffice (`$employee` variable defined) session
$isLanguageDefinedFromSession = (isset($language) && $language->isAssociatedToShop()) || defined('_PS_ADMIN_DIR_');

$useDefaultLanguage = $isNotValidLanguage || !$isLanguageDefinedFromSession;
if ($useDefaultLanguage) {
    // Default value for most cases
    $language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));

    // if `PS_LANG_DEFAULT` not a valid language for current shop then
    // use first valid language of the shop as default language.
    if($language->isMultishop() && !$language->isAssociatedToShop()) {
        $shopLanguages = $language->getLanguages(true, Context::getContext()->shop->id, false);

        if(isset($shopLanguages[0]['id_lang'])) {
            $shopDefaultLanguage = new Language($shopLanguages[0]['id_lang']);

            if(Validate::isLoadedObject($language)) {
                $language = $shopDefaultLanguage;
            }
        }
    }
}
if (!isset($language)) {
    // Default value for most cases
    $language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
}

$context->language = $language;

/* Get smarty */
require_once __DIR__ . '/smarty.config.inc.php';
/* @phpstan-ignore-next-line */
$context->smarty = $smarty;

if (!defined('_PS_ADMIN_DIR_')) {
    if (isset($cookie->id_customer) && (int) $cookie->id_customer) {
        $customer = new Customer((int) $cookie->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $context->cookie->logout();
        } else {
            $customer->logged = true;
            if ($customer->id_lang != $context->language->id) {
                $customer->id_lang = $context->language->id;
                $customer->update();
            }
        }
    }

    if (!isset($customer) || !Validate::isLoadedObject($customer)) {
        $customer = new Customer();

        /* Change the default group */
        if (Group::isFeatureActive()) {
            $customer->id_default_group = (int) Configuration::get('PS_UNIDENTIFIED_GROUP');
        }
    }
    $customer->id_guest = $cookie->id_guest;
    $context->customer = $customer;
}

/* Link should also be initialized in the context here for retrocompatibility */
$https_link = (Tools::usingSecureMode() && Configuration::get('PS_SSL_ENABLED')) ? 'https://' : 'http://';
$context->link = new Link($https_link, $https_link);

/*
 * @deprecated
 * USE : Configuration::get() method in order to getting the id of order status
 */

define('_PS_OS_CHEQUE_', Configuration::get('PS_OS_CHEQUE'));
define('_PS_OS_PAYMENT_', Configuration::get('PS_OS_PAYMENT'));
define('_PS_OS_PREPARATION_', Configuration::get('PS_OS_PREPARATION'));
define('_PS_OS_SHIPPING_', Configuration::get('PS_OS_SHIPPING'));
define('_PS_OS_DELIVERED_', Configuration::get('PS_OS_DELIVERED'));
define('_PS_OS_CANCELED_', Configuration::get('PS_OS_CANCELED'));
define('_PS_OS_REFUND_', Configuration::get('PS_OS_REFUND'));
define('_PS_OS_ERROR_', Configuration::get('PS_OS_ERROR'));
define('_PS_OS_OUTOFSTOCK_', Configuration::get('PS_OS_OUTOFSTOCK'));
define('_PS_OS_OUTOFSTOCK_PAID_', Configuration::get('PS_OS_OUTOFSTOCK_PAID'));
define('_PS_OS_OUTOFSTOCK_UNPAID_', Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'));
define('_PS_OS_BANKWIRE_', Configuration::get('PS_OS_BANKWIRE'));
define('_PS_OS_PAYPAL_', Configuration::get('PS_OS_PAYPAL'));
define('_PS_OS_WS_PAYMENT_', Configuration::get('PS_OS_WS_PAYMENT'));
define('_PS_OS_COD_VALIDATION_', Configuration::get('PS_OS_COD_VALIDATION'));

if (!defined('_MEDIA_SERVER_1_')) {
    define('_MEDIA_SERVER_1_', Configuration::get('PS_MEDIA_SERVER_1'));
}
if (!defined('_MEDIA_SERVER_2_')) {
    define('_MEDIA_SERVER_2_', Configuration::get('PS_MEDIA_SERVER_2'));
}
if (!defined('_MEDIA_SERVER_3_')) {
    define('_MEDIA_SERVER_3_', Configuration::get('PS_MEDIA_SERVER_3'));
}
