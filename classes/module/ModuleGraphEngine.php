<?php

use tools\profiling\Db;
use tools\profiling\Module;

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
abstract class ModuleGraphEngineCore extends Module
{
    protected $_type;

    public function __construct($type)
    {
        $this->_type = $type;
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        return Configuration::updateValue('PS_STATS_RENDER', $this->name);
    }

    public static function getGraphEngines()
    {
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
            SELECT m.`name`
            FROM `' . _DB_PREFIX_ . 'module` m
            LEFT JOIN `' . _DB_PREFIX_ . 'hook_module` hm ON hm.`id_module` = m.`id_module`
            LEFT JOIN `' . _DB_PREFIX_ . 'hook` h ON hm.`id_hook` = h.`id_hook`
            WHERE h.`name` = \'displayAdminStatsGraphEngine\'
        ');

        $array_engines = [];
        foreach ($result as $module) {
            $instance = Module::getInstanceByName($module['name']);
            if (!$instance) {
                continue;
            }
            $array_engines[$module['name']] = [$instance->displayName, $instance->description];
        }

        return $array_engines;
    }

    abstract public function createValues($values);

    abstract public function setSize($width, $height);

    abstract public function setLegend($legend);

    abstract public function setTitles($titles);

    abstract public function draw();
}
