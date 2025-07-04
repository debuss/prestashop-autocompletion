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
 * @since 1.5.0
 * @deprecated since 9.0 and will be removed in 10.0
 */
class WarehouseProductLocationCore extends ObjectModel
{
    /**
     * @var int product ID
     * */
    public $id_product;

    /**
     * @var int product attribute ID
     * */
    public $id_product_attribute;

    /**
     * @var int warehouse ID
     * */
    public $id_warehouse;

    /**
     * @var string location of the product
     * */
    public $location;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'warehouse_product_location',
        'primary' => 'id_warehouse_product_location',
        'fields' => [
            'location' => ['type' => self::TYPE_STRING, 'validate' => 'isReference', 'size' => 64],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_warehouse' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
        ],
    ];

    /**
     * @see ObjectModel::$webserviceParameters
     */
    protected $webserviceParameters = [
        'fields' => [
            'id_product' => ['xlink_resource' => 'products'],
            'id_product_attribute' => ['xlink_resource' => 'combinations'],
            'id_warehouse' => ['xlink_resource' => 'warehouses'],
        ],
        'hidden_fields' => [
        ],
    ];

    /**
     * For a given product and warehouse, gets the location.
     *
     * @param int $id_product product ID
     * @param int $id_product_attribute product attribute ID
     * @param int $id_warehouse warehouse ID
     *
     * @return string|false $location Location of the product
     */
    public static function getProductLocation($id_product, $id_product_attribute, $id_warehouse)
    {
        // build query
        $query = new DbQuery();
        $query->select('wpl.location');
        $query->from('warehouse_product_location', 'wpl');
        $query->where(
            'wpl.id_product = ' . (int) $id_product . '
			AND wpl.id_product_attribute = ' . (int) $id_product_attribute . '
			AND wpl.id_warehouse = ' . (int) $id_warehouse
        );

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * For a given product and warehouse, gets the WarehouseProductLocation corresponding ID.
     *
     * @param int $id_product
     * @param int $id_product_attribute
     * @param int $id_warehouse
     *
     * @return int $id_warehouse_product_location ID of the WarehouseProductLocation
     */
    public static function getIdByProductAndWarehouse($id_product, $id_product_attribute, $id_warehouse)
    {
        // build query
        $query = new DbQuery();
        $query->select('wpl.id_warehouse_product_location');
        $query->from('warehouse_product_location', 'wpl');
        $query->where(
            'wpl.id_product = ' . (int) $id_product . '
			AND wpl.id_product_attribute = ' . (int) $id_product_attribute . '
			AND wpl.id_warehouse = ' . (int) $id_warehouse
        );

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * For a given product, gets its warehouses.
     *
     * @param int $id_product
     *
     * @return PrestaShopCollection The type of the collection is WarehouseProductLocation
     */
    public static function getCollection($id_product)
    {
        $collection = new PrestaShopCollection('WarehouseProductLocation');
        $collection->where('id_product', '=', (int) $id_product);

        return $collection;
    }

    public static function getProducts($id_warehouse)
    {
        return Db::getInstance()->executeS('SELECT DISTINCT id_product FROM ' . _DB_PREFIX_ . 'warehouse_product_location WHERE id_warehouse=' . (int) $id_warehouse);
    }
}
