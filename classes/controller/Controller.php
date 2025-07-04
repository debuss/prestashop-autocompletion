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
use PrestaShopBundle\Translation\TranslatorComponent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use tools\profiling\Controller;
use tools\profiling\Hook;
use tools\profiling\Module;
use tools\profiling\Tools;

/**
 * @TODO Move undeclared variables and methods to this (base) class: $errors, $layout, checkLiveEditAccess, etc.
 *
 * @since 1.5.0
 */
abstract class ControllerCore
{
    public const SERVICE_LOCALE_REPOSITORY = 'prestashop.core.localization.locale.repository';
    public const SERVICE_MULTISTORE_FEATURE = 'prestashop.adapter.multistore_feature';

    /**
     * @var string|null
     */
    public $className;

    /**
     * @var Context
     */
    protected $context;

    /**
     * List of CSS files.
     *
     * @var array
     */
    public $css_files = [];

    /**
     * List of JavaScript files.
     *
     * @var array
     */
    public $js_files = [];

    /**
     * List of PHP errors.
     *
     * @var array
     */
    public static $php_errors = [];

    /**
     * Set to true to display page header.
     *
     * @var bool
     */
    protected $display_header;

    /**
     * Set to true to display page header javascript.
     *
     * @var bool
     */
    protected $display_header_javascript;

    /**
     * Template filename for the page content.
     *
     * @var string
     */
    protected $template;

    /**
     * Set to true to display page footer.
     *
     * @var bool
     */
    protected $display_footer;

    /**
     * Set to true to only render page content (used to get iframe content).
     *
     * @var bool
     */
    protected $content_only = false;

    /**
     * If AJAX parameter is detected in request, set this flag to true.
     *
     * @var bool
     */
    public $ajax = false;

    /**
     * If set to true, page content and messages will be encoded to JSON before responding to AJAX request.
     *
     * @var bool
     */
    protected $json = false;

    /**
     * JSON response status string.
     *
     * @var string
     */
    protected $status = '';

    /**
     * Redirect link. If not empty, the user will be redirected after initializing and processing input.
     *
     * @see Controller::run()
     *
     * @var string|null
     */
    protected $redirect_after = null;

    /**
     * Controller type. Possible values: 'front', 'modulefront', 'admin', 'moduleadmin'.
     *
     * @var string
     */
    public $controller_type;

    /**
     * Controller name.
     *
     * @var string
     */
    public $php_self;

    /**
     * @var TranslatorComponent
     */
    protected $translator;

    /**
     * Dependency container.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Module|null
     */
    public $module;

    /**
     * Check if the controller is available for the current user/visitor.
     */
    abstract public function checkAccess();

    /**
     * Check if the current user/visitor has valid view permissions.
     */
    abstract public function viewAccess();

    /**
     * Errors displayed after post processing
     *
     * @var array<string|int, string|bool>
     */
    public $errors = [];

    /** @var string */
    public $layout;

    /**
     * Initialize the page.
     *
     * @throws Exception
     */
    public function init()
    {
        Hook::exec(
            'actionControllerInitBefore',
            [
                'controller' => $this,
            ]
        );

        if (_PS_MODE_DEV_ && $this->controller_type == 'admin') {
            set_error_handler([__CLASS__, 'myErrorHandler']);
        }

        if (!defined('_PS_BASE_URL_')) {
            define('_PS_BASE_URL_', Tools::getShopDomain(true));
        }

        if (!defined('_PS_BASE_URL_SSL_')) {
            define('_PS_BASE_URL_SSL_', Tools::getShopDomainSsl(true));
        }

        if (null === $this->getContainer()) {
            $this->container = $this->buildContainer();
        }

        $localeRepo = $this->get(self::SERVICE_LOCALE_REPOSITORY);
        $this->context->currentLocale = $localeRepo->getLocale(
            $this->context->language->getLocale()
        );

        Hook::exec(
            'actionControllerInitAfter',
            [
                'controller' => $this,
            ]
        );
    }

    /**
     * Do the page treatment: process input, process AJAX, etc.
     */
    abstract public function postProcess();

    /**
     * Displays page view.
     */
    abstract public function display();

    /**
     * Sets default media list for this controller.
     */
    abstract public function setMedia();

    /**
     * returns a new instance of this controller.
     *
     * @param string $class_name
     * @param bool $auth
     * @param bool $ssl
     *
     * @return Controller
     */
    public static function getController($class_name, $auth = false, $ssl = false)
    {
        return new $class_name($auth, $ssl);
    }

    public function __construct()
    {
        if (null === $this->display_header) {
            $this->display_header = true;
        }
        if (null === $this->display_header_javascript) {
            $this->display_header_javascript = true;
        }
        if (null === $this->display_footer) {
            $this->display_footer = true;
        }
        $this->context = Context::getContext();
        $this->context->controller = $this;
        $this->translator = Context::getContext()->getTranslator();
        $this->ajax = $this->isAjax();

        if (
            !headers_sent()
            && isset($_SERVER['HTTP_USER_AGENT'])
            && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false
            || strpos($_SERVER['HTTP_USER_AGENT'], 'Trident') !== false)
        ) {
            header('X-UA-Compatible: IE=edge,chrome=1');
        }
    }

    /**
     * Returns if the current request is an AJAX request.
     *
     * @return bool
     */
    private function isAjax()
    {
        // Usage of ajax parameter is deprecated
        $isAjax = Tools::getValue('ajax') || Tools::isSubmit('ajax');

        if (isset($_SERVER['HTTP_ACCEPT'])) {
            $isAjax = $isAjax || preg_match(
                '#\bapplication/json\b#',
                $_SERVER['HTTP_ACCEPT']
            );
        }

        return $isAjax;
    }

    /**
     * Starts the controller process (this method should not be overridden!).
     */
    public function run()
    {
        $this->init();
        if ($this->checkAccess()) {
            // setMedia MUST be called before postProcess
            if (!$this->content_only && ($this->display_header || (isset($this->className) && $this->className))) {
                $this->setMedia();
            }

            // postProcess handles ajaxProcess
            $this->postProcess();

            if (!empty($this->redirect_after)) {
                $this->redirect();
            }

            if (!$this->content_only && ($this->display_header || (isset($this->className) && $this->className))) {
                $this->initHeader();
            }

            if ($this->viewAccess()) {
                $this->initContent();
            } else {
                $this->errors[] = $this->trans('Access denied.', [], 'Admin.Notifications.Error');
            }

            if (!$this->content_only && ($this->display_footer || (isset($this->className) && $this->className))) {
                $this->initFooter();
            }

            // Default behavior for ajax process is to use $_POST[action] or $_GET[action]
            // then using displayAjax[action]
            if ($this->ajax) {
                $action = Tools::toCamelCase(Tools::getValue('action'), true);

                if (!empty($action) && method_exists($this, 'displayAjax' . $action)) {
                    $this->{'displayAjax' . $action}();
                } elseif (method_exists($this, 'displayAjax')) {
                    $this->displayAjax();
                }
            } else {
                $this->display();
            }
        } else {
            $this->initCursedPage();
            $this->smartyOutputContent($this->layout);
        }
    }

    protected function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    /**
     * Sets page header display.
     *
     * @param bool $display
     */
    public function displayHeader($display = true)
    {
        $this->display_header = $display;
    }

    /**
     * Sets page header javascript display.
     *
     * @param bool $display
     */
    public function displayHeaderJavaScript($display = true)
    {
        $this->display_header_javascript = $display;
    }

    /**
     * Sets page footer display.
     *
     * @param bool $display
     */
    public function displayFooter($display = true)
    {
        $this->display_footer = $display;
    }

    /**
     * Sets template file for page content output.
     *
     * @param string $template
     */
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * Assigns Smarty variables for the page header.
     */
    abstract public function initHeader();

    /**
     * Assigns Smarty variables for the page main content.
     */
    abstract public function initContent();

    /**
     * Assigns Smarty variables when access is forbidden.
     */
    abstract public function initCursedPage();

    /**
     * Assigns Smarty variables for the page footer.
     */
    abstract public function initFooter();

    /**
     * Redirects to $this->redirect_after after the process if there is no error.
     */
    abstract protected function redirect();

    /**
     * Set $this->redirect_after that will be used by redirect() after the process.
     */
    public function setRedirectAfter($url)
    {
        $this->redirect_after = $url;
    }

    public function getRedirectAfter(): ?string
    {
        return $this->redirect_after;
    }

    /**
     * Adds a new stylesheet(s) to the page header.
     *
     * @param string|array $css_uri Path to CSS file, or list of css files like this : array(array(uri => media_type), ...)
     * @param string $css_media_type
     * @param int|null $offset
     * @param bool $check_path
     *
     * @return void
     */
    public function addCSS($css_uri, $css_media_type = 'all', $offset = null, $check_path = true)
    {
        if (!is_array($css_uri)) {
            $css_uri = [$css_uri];
        }

        foreach ($css_uri as $css_file => $media) {
            if (is_string($css_file) && strlen($css_file) > 1) {
                if ($check_path) {
                    $css_path = Media::getCSSPath($css_file, $media);
                } else {
                    $css_path = [$css_file => $media];
                }
            } else {
                if ($check_path) {
                    $css_path = Media::getCSSPath($media, $css_media_type);
                } else {
                    $css_path = [$media => $css_media_type];
                }
            }

            $key = is_array($css_path) ? key($css_path) : $css_path;
            if ($css_path && (!isset($this->css_files[$key]) || ($this->css_files[$key] != reset($css_path)))) {
                $size = count($this->css_files);
                if ($offset === null || $offset > $size || $offset < 0 || !is_numeric($offset)) {
                    $offset = $size;
                }

                $this->css_files = array_merge(array_slice($this->css_files, 0, $offset), $css_path, array_slice($this->css_files, $offset));
            }
        }
    }

    /**
     * Removes CSS stylesheet(s) from the queued stylesheet list.
     *
     * @param string|array $css_uri Path to CSS file or an array like: array(array(uri => media_type), ...)
     * @param string $css_media_type
     * @param bool $check_path
     */
    public function removeCSS($css_uri, $css_media_type = 'all', $check_path = true)
    {
        if (!is_array($css_uri)) {
            $css_uri = [$css_uri];
        }

        foreach ($css_uri as $css_file => $media) {
            if (is_string($css_file) && strlen($css_file) > 1) {
                if ($check_path) {
                    $css_path = Media::getCSSPath($css_file, $media);
                } else {
                    $css_path = [$css_file => $media];
                }
            } else {
                if ($check_path) {
                    $css_path = Media::getCSSPath($media, $css_media_type);
                } else {
                    $css_path = [$media => $css_media_type];
                }
            }

            if (
                $css_path
                && isset($this->css_files[key($css_path)])
                && ($this->css_files[key($css_path)] == reset($css_path))
            ) {
                unset($this->css_files[key($css_path)]);
            }
        }
    }

    /**
     * Adds a new JavaScript file(s) to the page header.
     *
     * @param string|array $js_uri Path to JS file or an array like: array(uri, ...)
     * @param bool $check_path
     */
    public function addJS($js_uri, $check_path = true)
    {
        if (!is_array($js_uri)) {
            $js_uri = [$js_uri];
        }

        foreach ($js_uri as $js_file) {
            $js_file = explode('?', $js_file);
            $version = '';
            if (isset($js_file[1]) && $js_file[1]) {
                $version = $js_file[1];
            }
            $js_path = $js_file = $js_file[0];
            if ($check_path) {
                $js_path = Media::getJSPath($js_file);
            }

            if ($js_path && !in_array($js_path, $this->js_files)) {
                $this->js_files[] = $js_path . ($version ? '?' . $version : '');
            }
        }
    }

    /**
     * Removes JS file(s) from the queued JS file list.
     *
     * @param string|array $js_uri Path to JS file or an array like: array(uri, ...)
     * @param bool $check_path
     */
    public function removeJS($js_uri, $check_path = true)
    {
        if (!is_array($js_uri)) {
            $js_uri = [$js_uri];
        }

        foreach ($js_uri as $js_file) {
            if ($check_path) {
                $js_file = Media::getJSPath($js_file);
            }

            if ($js_file && in_array($js_file, $this->js_files)) {
                unset($this->js_files[array_search($js_file, $this->js_files)]);
            }
        }
    }

    /**
     * Adds jQuery UI component(s) to queued JS file list.
     *
     * @param string|array $component
     * @param string $theme
     * @param bool $check_dependencies
     */
    public function addJqueryUI($component, $theme = 'base', $check_dependencies = true)
    {
        if (!is_array($component)) {
            $component = [$component];
        }

        foreach ($component as $ui) {
            $ui_path = Media::getJqueryUIPath($ui, $theme, $check_dependencies);
            $this->addCSS($ui_path['css'], 'all');
            $this->addJS($ui_path['js'], false);
        }
    }

    /**
     * Adds jQuery plugin(s) to queued JS file list.
     *
     * @param string|array $name
     * @param string|null $folder
     * @param bool $css
     */
    public function addJqueryPlugin($name, $folder = null, $css = true)
    {
        if (!is_array($name)) {
            $name = [$name];
        }

        foreach ($name as $plugin) {
            $plugin_path = Media::getJqueryPluginPath($plugin, $folder);

            if (!empty($plugin_path['js'])) {
                $this->addJS($plugin_path['js'], false);
            }
            if ($css && !empty($plugin_path['css'])) {
                $this->addCSS(key($plugin_path['css']), 'all', null, false);
            }
        }
    }

    /**
     * Checks if the controller has been called from XmlHttpRequest (AJAX).
     *
     * @since 1.5
     *
     * @return bool
     */
    public function isXmlHttpRequest()
    {
        return
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    public function getLayout()
    {
        // This is implemented by some children classes (e.g. FrontController)
        // but not required for all controllers.
        return null;
    }

    /**
     * Renders controller templates and generates page content.
     *
     * @param array|string $templates Template file(s) to be rendered
     *
     * @throws Exception
     * @throws SmartyException
     */
    protected function smartyOutputContent($templates)
    {
        $this->context->cookie->write();

        $js_tag = 'js_def';
        $this->context->smarty->assign($js_tag, Media::getJsDef());

        if (!is_array($templates)) {
            $templates = [$templates];
        }

        $html = '';
        foreach ($templates as $template) {
            $html .= $this->context->smarty->fetch($template, null, $this->getLayout());
        }

        echo trim($html);
    }

    /**
     * Checks if a template is cached.
     *
     * @param string $template
     * @param string|null $cache_id Cache item ID
     * @param string|null $compile_id
     *
     * @return bool
     */
    protected function isCached($template, $cache_id = null, $compile_id = null)
    {
        Tools::enableCache();
        $isCached = $this->context->smarty->isCached($template, $cache_id, $compile_id);
        Tools::restoreCacheSettings();

        return $isCached;
    }

    /**
     * Custom error handler.
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     *
     * @return bool
     */
    public static function myErrorHandler($errno, $errstr, $errfile, $errline)
    {
        /**
         * Prior to PHP 8.0.0, the $errno value was always 0 if the expression which caused the diagnostic was prepended by the @ error-control operator.
         *
         * @see https://www.php.net/manual/fr/function.set-error-handler.php
         * @see https://www.php.net/manual/en/language.operators.errorcontrol.php
         */
        if (!(error_reporting() & $errno)) {
            return false;
        }

        switch ($errno) {
            case E_USER_ERROR:
            case E_ERROR:
                die('Fatal error: ' . $errstr . ' in ' . $errfile . ' on line ' . $errline);
            case E_USER_WARNING:
            case E_WARNING:
                $type = 'Warning';

                break;
            case E_USER_NOTICE:
            case E_NOTICE:
                $type = 'Notice';

                break;
            default:
                $type = 'Unknown error';

                break;
        }

        Controller::$php_errors[] = [
            'type' => $type,
            'errline' => (int) $errline,
            'errfile' => str_replace('\\', '\\\\', $errfile), // Hack for Windows paths
            'errno' => (int) $errno,
            'errstr' => $errstr,
        ];
        Context::getContext()->smarty->assign('php_errors', Controller::$php_errors);

        return true;
    }

    /**
     * @param string|null $value
     * @param string|null $controller
     * @param string|null $method
     *
     * @throws PrestaShopException
     */
    protected function ajaxRender($value = null, $controller = null, $method = null)
    {
        if ($controller === null) {
            $controller = get_class($this);
        }

        if ($method === null) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $method = $bt[1]['function'];
        }

        Hook::exec('actionAjaxDie' . $controller . $method . 'Before', ['value' => &$value]);
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        header('X-Robots-Tag: noindex, nofollow', true);

        echo $value;
    }

    /**
     * Construct the dependency container.
     *
     * @return ContainerInterface
     */
    protected function buildContainer(): ContainerInterface
    {
        return SymfonyContainer::getInstance();
    }

    /**
     * Gets a service from the service container.
     *
     * @param string $serviceId Service identifier
     *
     * @return object The associated service
     *
     * @throws Exception
     */
    public function get($serviceId)
    {
        return $this->container->get($serviceId);
    }

    /**
     * Gets a parameter.
     *
     * @param string $parameterId The parameter name
     *
     * @return mixed The parameter value
     *
     * @throws InvalidArgumentException if the parameter is not defined
     */
    public function getParameter($parameterId)
    {
        return $this->container->getParameter($parameterId);
    }

    /**
     * Gets the dependency container.
     *
     * @return ContainerInterface|null
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Check if multistore feature is enabled.
     *
     * @return bool
     */
    public function isMultistoreEnabled(): bool
    {
        return $this->get(static::SERVICE_MULTISTORE_FEATURE)->isUsed();
    }
}
