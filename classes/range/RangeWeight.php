<?php

use tools\profiling\Db;
use tools\profiling\ObjectModel;

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
class RangeWeightCore extends ObjectModel
{
    /**
     * @var int
     */
    public $id_carrier;

    /**
     * @var float
     */
    public $delimiter1;

    /**
     * @var float
     */
    public $delimiter2;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'range_weight',
        'primary' => 'id_range_weight',
        'fields' => [
            'id_carrier' => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true],
            'delimiter1' => ['type' => self::TYPE_FLOAT, 'validate' => 'isUnsignedFloat', 'required' => true],
            'delimiter2' => ['type' => self::TYPE_FLOAT, 'validate' => 'isUnsignedFloat', 'required' => true],
        ],
    ];

    protected $webserviceParameters = [
        'objectNodeName' => 'weight_range',
        'objectsNodeName' => 'weight_ranges',
        'fields' => [
            'id_carrier' => ['xlink_resource' => 'carriers'],
        ],
    ];

    /**
     * Override add to create delivery value for all zones.
     *
     * @see classes/ObjectModelCore::add()
     *
     * @param bool $null_values
     * @param bool $autodate
     *
     * @return bool Insertion result
     */
    public function add($autodate = true, $null_values = false)
    {
        if (!parent::add($autodate, $null_values) || !Validate::isLoadedObject($this)) {
            return false;
        }
        if (defined('PS_INSTALLATION_IN_PROGRESS')) {
            return true;
        }
        $carrier = new Carrier((int) $this->id_carrier);
        $price_list = [];
        foreach ($carrier->getZones() as $zone) {
            $price_list[] = [
                'id_range_price' => null,
                'id_range_weight' => (int) $this->id,
                'id_carrier' => (int) $this->id_carrier,
                'id_zone' => (int) $zone['id_zone'],
                'price' => 0,
            ];
        }
        $carrier->addDeliveryPrice($price_list);

        return true;
    }

    /**
     * Get all available weight ranges.
     *
     * @param int $id_carrier Carrier identifier
     *
     * @return array|false All ranges for this carrier, or false on error
     */
    public static function getRanges($id_carrier)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
            SELECT *
            FROM `' . _DB_PREFIX_ . 'range_weight`
            WHERE `id_carrier` = ' . (int) $id_carrier . '
            ORDER BY `delimiter1` ASC');
    }

    /**
     * Check if a range exists for delimiter1 and delimiter2 by id_carrier or id_reference
     *
     * @param int|null $id_carrier Carrier identifier
     * @param float $delimiter1
     * @param float $delimiter2
     * @param int|null $id_reference Carrier reference is the initial Carrier identifier (optional)
     *
     * @return int|false Total of existing ranges, or false on error
     */
    public static function rangeExist($id_carrier, $delimiter1, $delimiter2, $id_reference = null)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
            SELECT count(*)
            FROM `' . _DB_PREFIX_ . 'range_weight` rw' .
            (null === $id_carrier && $id_reference ? '
            INNER JOIN `' . _DB_PREFIX_ . 'carrier` c on (rw.`id_carrier` = c.`id_carrier`)' : '') . '
            WHERE' .
            ($id_carrier ? ' `id_carrier` = ' . (int) $id_carrier : '') .
            (null === $id_carrier && $id_reference ? ' c.`id_reference` = ' . (int) $id_reference : '') . '
            AND `delimiter1` = ' . (float) $delimiter1 . ' AND `delimiter2` = ' . (float) $delimiter2);
    }

    /**
     * Check if a range overlaps another range for this carrier
     *
     * @param int $id_carrier Carrier identifier
     * @param float $delimiter1
     * @param float $delimiter2
     * @param int|null $id_rang RangeWeight identifier (optional)
     *
     * @return int|false Total of overlapping ranges, or false on error
     */
    public static function isOverlapping($id_carrier, $delimiter1, $delimiter2, $id_rang = null)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
            SELECT count(*)
            FROM `' . _DB_PREFIX_ . 'range_weight`
            WHERE `id_carrier` = ' . (int) $id_carrier . '
            AND ((`delimiter1` >= ' . (float) $delimiter1 . ' AND `delimiter1` < ' . (float) $delimiter2 . ')
                OR (`delimiter2` > ' . (float) $delimiter1 . ' AND `delimiter2` < ' . (float) $delimiter2 . ')
                OR (' . (float) $delimiter1 . ' > `delimiter1` AND ' . (float) $delimiter1 . ' < `delimiter2`)
                OR (' . (float) $delimiter2 . ' < `delimiter1` AND ' . (float) $delimiter2 . ' > `delimiter2`)
            )
            ' . (null !== $id_rang ? ' AND `id_range_weight` != ' . (int) $id_rang : ''));
    }
}
