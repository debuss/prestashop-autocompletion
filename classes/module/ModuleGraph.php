<?php

use tools\profiling\Module;
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
abstract class ModuleGraphCore extends Module
{
    protected $_employee;

    /** @var array of integers graph data */
    protected $_values = [];

    /** @var array of strings graph legends (X axis) */
    protected $_legend = [];

    /** @var array string graph titles */
    protected $_titles = ['main' => null, 'x' => null, 'y' => null];

    /** @var ModuleGraphEngine graph engine */
    protected $_render;

    /** @var int */
    protected $_id_lang;

    /** @var string */
    protected $_csv;

    abstract protected function getData($layers);

    public function setEmployee($id_employee)
    {
        $this->_employee = new Employee($id_employee);
    }

    public function setLang($id_lang)
    {
        $this->_id_lang = $id_lang;
    }

    protected function setDateGraph($layers, $legend = false)
    {
        // Get dates in a manageable format
        $from_array = getdate(strtotime($this->_employee->stats_date_from));
        $to_array = getdate(strtotime($this->_employee->stats_date_to));

        // If the granularity is inferior to 1 day
        if ($this->_employee->stats_date_from == $this->_employee->stats_date_to) {
            if ($legend) {
                for ($i = 0; $i < 24; ++$i) {
                    if ($layers == 1) {
                        $this->_values[$i] = 0;
                    } else {
                        for ($j = 0; $j < $layers; ++$j) {
                            $this->_values[$j][$i] = 0;
                        }
                    }
                    $this->_legend[$i] = ($i % 2) ? '' : sprintf('%02dh', $i);
                }
            }
            if (is_callable([$this, 'setDayValues'])) {
                $this->setDayValues($layers);
            }
        } elseif (strtotime($this->_employee->stats_date_to) - strtotime($this->_employee->stats_date_from) <= 2678400) {
            // If the granularity is inferior to 1 month
            // @TODO : change to manage 28 to 31 days

            if ($legend) {
                $days = [];
                if ($from_array['mon'] == $to_array['mon']) {
                    for ($i = $from_array['mday']; $i <= $to_array['mday']; ++$i) {
                        $days[] = $i;
                    }
                } else {
                    $imax = date('t', mktime(0, 0, 0, $from_array['mon'], 1, $from_array['year']));
                    for ($i = $from_array['mday']; $i <= $imax; ++$i) {
                        $days[] = $i;
                    }

                    for ($i = 1; $i <= $to_array['mday']; ++$i) {
                        $days[] = $i;
                    }
                }

                foreach ($days as $i) {
                    if ($layers == 1) {
                        $this->_values[$i] = 0;
                    } else {
                        for ($j = 0; $j < $layers; ++$j) {
                            $this->_values[$j][$i] = 0;
                        }
                    }

                    $this->_legend[$i] = ($i % 2) ? '' : sprintf('%02d', $i);
                }
            }

            if (is_callable([$this, 'setMonthValues'])) {
                $this->setMonthValues($layers);
            }
        } elseif (strtotime('-1 year', strtotime($this->_employee->stats_date_to)) < strtotime($this->_employee->stats_date_from)) {
            // If the granularity is less than 1 year

            if ($legend) {
                $months = [];
                if ($from_array['year'] == $to_array['year']) {
                    for ($i = $from_array['mon']; $i <= $to_array['mon']; ++$i) {
                        $months[] = $i;
                    }
                } else {
                    for ($i = $from_array['mon']; $i <= 12; ++$i) {
                        $months[] = $i;
                    }
                    for ($i = 1; $i <= $to_array['mon']; ++$i) {
                        $months[] = $i;
                    }
                }
                foreach ($months as $i) {
                    if ($layers == 1) {
                        $this->_values[$i] = 0;
                    } else {
                        for ($j = 0; $j < $layers; ++$j) {
                            $this->_values[$j][$i] = 0;
                        }
                    }
                    $this->_legend[$i] = sprintf('%02d', $i);
                }
            }
            if (is_callable([$this, 'setYearValues'])) {
                $this->setYearValues($layers);
            }
        } else {
            // If the granularity is greater than 1 year

            if ($legend) {
                $years = [];
                for ($i = $from_array['year']; $i <= $to_array['year']; ++$i) {
                    $years[] = $i;
                }

                foreach ($years as $i) {
                    if ($layers == 1) {
                        $this->_values[$i] = 0;
                    } else {
                        for ($j = 0; $j < $layers; ++$j) {
                            $this->_values[$j][$i] = 0;
                        }
                    }
                    $this->_legend[$i] = sprintf('%04d', $i);
                }
            }

            if (is_callable([$this, 'setAllTimeValues'])) {
                $this->setAllTimeValues($layers);
            }
        }
    }

    protected function csvExport($datas)
    {
        $context = Context::getContext();

        $this->setEmployee($context->employee->id);
        $this->setLang($context->language->id);

        $layers = isset($datas['layers']) ? $datas['layers'] : 1;
        if (isset($datas['option'])) {
            $this->setOption($datas['option'], $layers);
        }

        $this->getData($layers);

        // @todo use native CSV PHP functions ?
        // Generate first line (column titles)
        if (is_array($this->_titles['main'])) {
            for ($i = 0, $total_main = count($this->_titles['main']); $i <= $total_main; ++$i) {
                if ($i > 0) {
                    $this->_csv .= ';';
                }
                if (isset($this->_titles['main'][$i])) {
                    $this->_csv .= $this->escapeCell($this->_titles['main'][$i]);
                }
            }
        } else { // If there is only one column title, there is in fast two column (the first without title)
            $this->_csv .= ';' . $this->escapeCell($this->_titles['main']);
        }

        $this->_csv .= "\n";
        if (count($this->_legend)) {
            $total = 0;

            if ($datas['type'] == 'pie') {
                foreach ($this->_legend as $key => $legend) {
                    for ($i = 0, $total_main = (is_array($this->_titles['main']) ? count($this->_values) : 1); $i < $total_main; ++$i) {
                        $total += (is_array($this->_values[$i]) ? $this->_values[$i][$key] : $this->_values[$key]);
                    }
                }
            }

            foreach ($this->_legend as $key => $legend) {
                $this->_csv .= $this->escapeCell($legend) . ';';
                for ($i = 0, $total_main = (is_array($this->_titles['main']) ? count($this->_values) : 1); $i < $total_main; ++$i) {
                    if (!isset($this->_values[$i]) || !is_array($this->_values[$i])) {
                        if (isset($this->_values[$key])) {
                            // We don't want strings to be divided. Example: product name
                            if (is_numeric($this->_values[$key])) {
                                $this->_csv .= $this->_values[$key] / (($datas['type'] == 'pie') ? $total : 1);
                            } else {
                                $this->_csv .= $this->escapeCell($this->_values[$key]);
                            }
                        } else {
                            $this->_csv .= '0';
                        }
                    } else {
                        // We don't want strings to be divided. Example: product name
                        if (is_numeric($this->_values[$i][$key])) {
                            $this->_csv .= $this->_values[$i][$key] / (($datas['type'] == 'pie') ? $total : 1);
                        } else {
                            $this->_csv .= $this->escapeCell($this->_values[$i][$key]);
                        }
                    }
                    $this->_csv .= ';';
                }
                $this->_csv .= "\n";
            }
        }

        $this->_displayCsv();
    }

    protected function _displayCsv()
    {
        if (ob_get_level() && ob_get_length() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $this->displayName . ' - ' . time() . '.csv"');
        echo $this->_csv;
        exit;
    }

    public function create($render, $type, $width, $height, $layers)
    {
        if (!Validate::isModuleName($render)) {
            throw new PrestaShopException('Invalid graph module name.');
        }
        if (!Tools::file_exists_cache($file = _PS_ROOT_DIR_ . '/modules/' . $render . '/' . $render . '.php')) {
            throw new PrestaShopException('Main graph module file does not exist.');
        }
        require_once $file;
        $this->_render = new $render($type);

        $this->getData($layers);
        $this->_render->createValues($this->_values);
        $this->_render->setSize($width, $height);
        $this->_render->setLegend($this->_legend);
        $this->_render->setTitles($this->_titles);
    }

    public function draw()
    {
        $this->_render->draw();
    }

    /**
     * @todo Set this method as abstracted ? Quid of module compatibility.
     */
    public function setOption($option, $layers = 1)
    {
    }

    public function engine($params)
    {
        $context = Context::getContext();
        if (!($render = Configuration::get('PS_STATS_RENDER'))) {
            return Context::getContext()->getTranslator()->trans('No graph engine selected', [], 'Admin.Modules.Notification');
        }
        if (!Validate::isModuleName($render)) {
            throw new PrestaShopException('Invalid graph module name.');
        }
        if (!file_exists(_PS_ROOT_DIR_ . '/modules/' . $render . '/' . $render . '.php')) {
            return Context::getContext()->getTranslator()->trans('Graph engine selected is unavailable.', [], 'Admin.Modules.Notification');
        }

        $id_employee = (int) $context->employee->id;
        $id_lang = (int) $context->language->id;

        if (!isset($params['layers'])) {
            $params['layers'] = 1;
        }
        if (!isset($params['type'])) {
            $params['type'] = 'column';
        }
        if (!isset($params['width'])) {
            $params['width'] = '100%';
        }
        if (!isset($params['height'])) {
            $params['height'] = 270;
        }

        $url_params = $params;
        $url_params['render'] = $render;
        $url_params['module'] = Tools::getValue('module');
        $url_params['id_employee'] = $id_employee;
        $url_params['id_lang'] = $id_lang;
        $url_params['ajax'] = 1;
        $url_params['action'] = 'graphDraw';
        $drawer = Context::getContext()->link->getAdminLink('AdminStats', true, [], array_map('tools\profiling\Tools', $url_params));

        require_once _PS_ROOT_DIR_ . '/modules/' . $render . '/' . $render . '.php';

        return call_user_func([$render, 'hookGraphEngine'], $params, $drawer);
    }

    protected static function getEmployee($employee = null, ?Context $context = null)
    {
        if (!Validate::isLoadedObject($employee)) {
            if (!$context) {
                $context = Context::getContext();
            }
            if (!Validate::isLoadedObject($context->employee)) {
                return false;
            }
            $employee = $context->employee;
        }

        if (empty($employee->stats_date_from) || empty($employee->stats_date_to)
            || $employee->stats_date_from == '0000-00-00' || $employee->stats_date_to == '0000-00-00') {
            if (empty($employee->stats_date_from) || $employee->stats_date_from == '0000-00-00') {
                $employee->stats_date_from = date('Y') . '-01-01';
            }
            if (empty($employee->stats_date_to) || $employee->stats_date_to == '0000-00-00') {
                $employee->stats_date_to = date('Y') . '-12-31';
            }
            $employee->update();
        }

        return $employee;
    }

    public function getDate()
    {
        return static::getDateBetween($this->_employee);
    }

    public static function getDateBetween($employee = null)
    {
        if ($employee = static::getEmployee($employee)) {
            return ' \'' . pSQL($employee->stats_date_from) . ' 00:00:00\' AND \'' . pSQL($employee->stats_date_to) . ' 23:59:59\' ';
        }

        return ' \'' . date('Y-m') . '-01 00:00:00\' AND \'' . date('Y-m-t') . ' 23:59:59\' ';
    }

    public function getLang()
    {
        return $this->_id_lang;
    }

    /**
     * Escape cell content.
     * If the content begins with =+-@ a quote is added at the beginning of
     * the string.
     * In all situation, add double quote to encapsulate the content.
     *
     * @param string $content
     *
     * @return string
     */
    public function escapeCell(string $content): string
    {
        $escaped = '"';
        if (preg_match('~^[=+\-@]~', $content)) {
            $content = '\'' . $content;
        }

        $escaped .= str_replace('"', '""', $content);
        $escaped .= '"';

        return $escaped;
    }
}
