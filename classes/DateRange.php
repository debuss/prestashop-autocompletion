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

use tools\profiling\Db;
use tools\profiling\ObjectModel;

/**
 * Class DateRangeCore.
 */
class DateRangeCore extends ObjectModel
{
    /** @var string */
    public $time_start;

    /** @var string */
    public $time_end;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'date_range',
        'primary' => 'id_date_range',
        'fields' => [
            'time_start' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'time_end' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    /**
     * Get current range.
     *
     * @return mixed
     */
    public static function getCurrentRange()
    {
        $result = Db::getInstance()->getRow('
		SELECT `id_date_range`, `time_end`
		FROM `' . _DB_PREFIX_ . 'date_range`
		WHERE `time_end` = (SELECT MAX(`time_end`) FROM `' . _DB_PREFIX_ . 'date_range`)');
        if (!isset($result['id_date_range']) || strtotime($result['time_end']) < strtotime(date('Y-m-d H:i:s'))) {
            // The default range is set to 1 day less 1 second (in seconds)
            $rangeSize = 86399;
            $dateRange = new DateRange();
            $dateRange->time_start = date('Y-m-d');
            $dateRange->time_end = strftime('%Y-%m-%d %H:%M:%S', strtotime($dateRange->time_start) + $rangeSize);
            $dateRange->add();

            return $dateRange->id;
        }

        return $result['id_date_range'];
    }
}
