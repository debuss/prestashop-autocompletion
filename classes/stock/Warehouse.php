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
 * Holds Stock.
 *
 * @since 1.5.0
 * @deprecated since 9.0 and will be removed in 10.0
 */
class WarehouseCore extends ObjectModel
{
    /** @var int identifier of the warehouse */
    public $id;

    /** @var int Id of the address associated to the warehouse */
    public $id_address;

    /** @var string Reference of the warehouse */
    public $reference;

    /** @var string Name of the warehouse */
    public $name;

    /** @var int Id of the employee who manages the warehouse */
    public $id_employee;

    /** @var int Id of the valuation currency of the warehouse */
    public $id_currency;

    /** @var bool True if warehouse has been deleted (hence, no deletion in DB) */
    public $deleted = false;

    /**
     * Describes the way a Warehouse is managed.
     *
     * @var string enum WA|LIFO|FIFO
     */
    public $management_type;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'warehouse',
        'primary' => 'id_warehouse',
        'fields' => [
            'id_address' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'reference' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 64],
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 45],
            'id_employee' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'management_type' => ['type' => self::TYPE_STRING, 'validate' => 'isStockManagement', 'required' => true],
            'id_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'deleted' => ['type' => self::TYPE_BOOL],
        ],
    ];

    /**
     * @see ObjectModel::$webserviceParameters
     */
    protected $webserviceParameters = [
        'fields' => [
            'id_address' => ['xlink_resource' => 'addresses'],
            'id_employee' => ['xlink_resource' => 'employees'],
            'id_currency' => ['xlink_resource' => 'currencies'],
            'valuation' => ['getter' => 'getWsStockValue', 'setter' => false],
            'deleted' => [],
        ],
        'associations' => [
            'stocks' => [
                'resource' => 'stock',
                'fields' => [
                    'id' => [],
                ],
            ],
            'carriers' => [
                'resource' => 'carrier',
                'fields' => [
                    'id' => [],
                ],
            ],
            'shops' => [
                'resource' => 'shop',
                'fields' => [
                    'id' => [],
                    'name' => [],
                ],
            ],
        ],
    ];

    /**
     * Gets the shops associated to the current warehouse.
     *
     * @return array Shops (id, name)
     */
    public function getShops()
    {
        $query = new DbQuery();
        $query->select('ws.id_shop, s.name');
        $query->from('warehouse_shop', 'ws');
        $query->leftJoin('shop', 's', 's.id_shop = ws.id_shop');
        $query->where($this->def['primary'] . ' = ' . (int) $this->id);

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

        return $res;
    }

    /**
     * Gets the carriers associated to the current warehouse.
     *
     * @return array Ids of the associated carriers
     */
    public function getCarriers($return_reference = false)
    {
        $ids_carrier = [];

        $query = new DbQuery();
        if ($return_reference) {
            $query->select('wc.id_carrier');
        } else {
            $query->select('c.id_carrier');
        }
        $query->from('warehouse_carrier', 'wc');
        $query->innerJoin('carrier', 'c', 'c.id_reference = wc.id_carrier');
        $query->where($this->def['primary'] . ' = ' . (int) $this->id);
        $query->where('c.deleted = 0');
        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

        if (!is_array($res)) {
            return $ids_carrier;
        }

        foreach ($res as $carriers) {
            foreach ($carriers as $carrier) {
                $ids_carrier[$carrier] = $carrier;
            }
        }

        return $ids_carrier;
    }

    /**
     * Sets the carriers associated to the current warehouse.
     *
     * @param array $ids_carriers
     */
    public function setCarriers($ids_carriers)
    {
        if (!is_array($ids_carriers)) {
            $ids_carriers = [];
        }

        $row_to_insert = [];
        foreach ($ids_carriers as $id_carrier) {
            $row_to_insert[] = [$this->def['primary'] => $this->id, 'id_carrier' => (int) $id_carrier];
        }

        Db::getInstance()->execute('
			DELETE FROM ' . _DB_PREFIX_ . 'warehouse_carrier
			WHERE ' . $this->def['primary'] . ' = ' . (int) $this->id);

        if ($row_to_insert) {
            Db::getInstance()->insert('warehouse_carrier', $row_to_insert);
        }
    }

    /**
     * For a given carrier, removes it from the warehouse/carrier association
     * If $id_warehouse is set, it only removes the carrier for this warehouse.
     *
     * @param int $id_carrier Id of the carrier to remove
     * @param int $id_warehouse optional Id of the warehouse to filter
     */
    public static function removeCarrier($id_carrier, $id_warehouse = null)
    {
        Db::getInstance()->execute('
			DELETE FROM ' . _DB_PREFIX_ . 'warehouse_carrier
			WHERE id_carrier = ' . (int) $id_carrier .
            ($id_warehouse ? ' AND id_warehouse = ' . (int) $id_warehouse : ''));
    }

    /**
     * Checks if a warehouse is empty - i.e. has no stock.
     *
     * @return bool
     */
    public function isEmpty()
    {
        $query = new DbQuery();
        $query->select('SUM(s.physical_quantity)');
        $query->from('stock', 's');
        $query->where($this->def['primary'] . ' = ' . (int) $this->id);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query) == 0;
    }

    /**
     * Checks if the given warehouse exists.
     *
     * @param int $id_warehouse
     *
     * @return bool Exists/Does not exist
     */
    public static function exists($id_warehouse)
    {
        $query = new DbQuery();
        $query->select('id_warehouse');
        $query->from('warehouse');
        $query->where('id_warehouse = ' . (int) $id_warehouse);
        $query->where('deleted = 0');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query, false);
    }

    /**
     * For a given {product, product attribute} sets its location in the given warehouse
     * First, for the given parameters, it cleans the database before updating.
     *
     * @param int $id_product ID of the product
     * @param int $id_product_attribute Use 0 if this product does not have attributes
     * @param int $id_warehouse ID of the warehouse
     * @param string $location Describes the location (no lang id required)
     *
     * @return bool Success/Failure
     */
    public static function setProductLocation($id_product, $id_product_attribute, $id_warehouse, $location)
    {
        Db::getInstance()->execute('
			DELETE FROM `' . _DB_PREFIX_ . 'warehouse_product_location`
			WHERE `id_product` = ' . (int) $id_product . '
			AND `id_product_attribute` = ' . (int) $id_product_attribute . '
			AND `id_warehouse` = ' . (int) $id_warehouse);

        $row_to_insert = [
            'id_product' => (int) $id_product,
            'id_product_attribute' => (int) $id_product_attribute,
            'id_warehouse' => (int) $id_warehouse,
            'location' => pSQL($location),
        ];

        return Db::getInstance()->insert('warehouse_product_location', $row_to_insert);
    }

    /**
     * Resets all product locations for this warehouse.
     */
    public function resetProductsLocations()
    {
        Db::getInstance()->execute('
			DELETE FROM `' . _DB_PREFIX_ . 'warehouse_product_location`
			WHERE `id_warehouse` = ' . (int) $this->id);
    }

    /**
     * For a given {product, product attribute} gets its location in the given warehouse.
     *
     * @param int $id_product ID of the product
     * @param int $id_product_attribute Use 0 if this product does not have attributes
     * @param int $id_warehouse ID of the warehouse
     *
     * @return string Location of the product
     */
    public static function getProductLocation($id_product, $id_product_attribute, $id_warehouse)
    {
        $query = new DbQuery();
        $query->select('location');
        $query->from('warehouse_product_location');
        $query->where('id_warehouse = ' . (int) $id_warehouse);
        $query->where('id_product = ' . (int) $id_product);
        $query->where('id_product_attribute = ' . (int) $id_product_attribute);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * For a given {product, product attribute} gets warehouse list.
     *
     * @param int $id_product ID of the product
     * @param int $id_product_attribute Optional, uses 0 if this product does not have attributes
     * @param int $id_shop Optional, ID of the shop. Uses the context shop id (@see Context::shop)
     *
     * @return array Warehouses (ID, reference/name concatenated)
     */
    public static function getProductWarehouseList($id_product, $id_product_attribute = 0, $id_shop = null)
    {
        // if it's a pack, returns warehouses if and only if some products use the advanced stock management
        if ($id_shop === null) {
            if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                $shop_group = Shop::getContextShopGroup();
                $shop_group_id = (int) $shop_group->id;
            } else {
                $shop_group = Context::getContext()->shop->getGroup();
                $shop_group_id = (int) $shop_group->id;
                $id_shop = (int) Context::getContext()->shop->id;
            }
            $share_stock = $shop_group->share_stock;
        } else {
            $shop_group = Shop::getGroupFromShop($id_shop, false);
            $share_stock = $shop_group['share_stock'];
            $shop_group_id = (int) $shop_group['id'];
        }

        if ($share_stock && $shop_group_id) {
            $ids_shop = Shop::getShops(true, $shop_group_id, true);
        } else {
            $ids_shop = [(int) $id_shop];
        }

        $query = new DbQuery();
        $query->select('wpl.id_warehouse, CONCAT(w.reference, " - ", w.name) as name');
        $query->from('warehouse_product_location', 'wpl');
        $query->innerJoin('warehouse_shop', 'ws', 'ws.id_warehouse = wpl.id_warehouse AND id_shop IN (' . implode(',', array_map('intval', $ids_shop)) . ')');
        $query->innerJoin('warehouse', 'w', 'ws.id_warehouse = w.id_warehouse');
        $query->where('id_product = ' . (int) $id_product);
        $query->where('id_product_attribute = ' . (int) $id_product_attribute);
        $query->where('w.deleted = 0');
        $query->groupBy('wpl.id_warehouse');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
    }

    /**
     * Gets available warehouses
     * It is possible via ignore_shop and id_shop to filter the list with shop id.
     *
     * @param bool $ignore_shop Optional, false by default - Allows to get only the warehouses that are associated to one/some shops (@see $id_shop)
     * @param int $id_shop optional, Context::shop::Id by default - Allows to define a specific shop to filter
     *
     * @return array Warehouses (ID, reference/name concatenated)
     */
    public static function getWarehouses($ignore_shop = false, $id_shop = null)
    {
        if (!$ignore_shop) {
            if (null === $id_shop) {
                $id_shop = Context::getContext()->shop->id;
            }
        }

        $query = new DbQuery();
        $query->select('w.id_warehouse, CONCAT(reference, \' - \', name) as name');
        $query->from('warehouse', 'w');
        $query->where('deleted = 0');
        $query->orderBy('reference ASC');
        if (!$ignore_shop) {
            $query->innerJoin('warehouse_shop', 'ws', 'ws.id_warehouse = w.id_warehouse AND ws.id_shop = ' . (int) $id_shop);
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
    }

    /**
     * Gets warehouses grouped by shops.
     *
     * @return array (of array) Warehouses ID are grouped by shops ID
     */
    public static function getWarehousesGroupedByShops()
    {
        $ids_warehouse = [];
        $query = new DbQuery();
        $query->select('id_warehouse, id_shop');
        $query->from('warehouse_shop');
        $query->orderBy('id_shop');

        // queries to get warehouse ids grouped by shops
        foreach (Db::getInstance()->executeS($query) as $row) {
            $ids_warehouse[$row['id_shop']][] = $row['id_warehouse'];
        }

        return $ids_warehouse;
    }

    /**
     * Gets the number of products in the current warehouse.
     *
     * @return int Number of different id_stock
     */
    public function getNumberOfProducts()
    {
        $query = 'SELECT COUNT(t.id_stock) FROM
            (
                SELECT s.id_stock
                FROM ' . _DB_PREFIX_ . 'stock s
                WHERE s.id_warehouse = ' . (int) $this->id . '
                GROUP BY s.id_product, s.id_product_attribute
             ) as t';

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * Gets the number of quantities - for all products - in the current warehouse.
     *
     * @return int Total Quantity
     */
    public function getQuantitiesOfProducts()
    {
        $query = '
			SELECT SUM(s.physical_quantity)
			FROM ' . _DB_PREFIX_ . 'stock s
			WHERE s.id_warehouse = ' . (int) $this->id;

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);

        return $res ? $res : 0;
    }

    /**
     * Gets the value of the stock in the current warehouse.
     *
     * @return float Value of the stock
     */
    public function getStockValue()
    {
        $query = new DbQuery();
        $query->select('SUM(s.`price_te` * s.`physical_quantity`)');
        $query->from('stock', 's');
        $query->where('s.`id_warehouse` = ' . (int) $this->id);

        return (float) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * For a given employee, gets the warehouse(s) he/she manages.
     *
     * @param int $id_employee Manager ID
     *
     * @return array ids_warehouse Ids of the warehouses
     */
    public static function getWarehousesByEmployee($id_employee)
    {
        $query = new DbQuery();
        $query->select('w.id_warehouse');
        $query->from('warehouse', 'w');
        $query->where('w.id_employee = ' . (int) $id_employee);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
    }

    /**
     * For a given product, returns the warehouses it is stored in.
     *
     * @param int $id_product Product Id
     * @param int $id_product_attribute Optional, Product Attribute Id - 0 by default (no attribues)
     *
     * @return array Warehouses Ids and names
     */
    public static function getWarehousesByProductId($id_product, $id_product_attribute = 0)
    {
        if (!$id_product && !$id_product_attribute) {
            return [];
        }

        $query = new DbQuery();
        $query->select('DISTINCT w.id_warehouse, CONCAT(w.reference, " - ", w.name) as name');
        $query->from('warehouse', 'w');
        $query->leftJoin('warehouse_product_location', 'wpl', 'wpl.id_warehouse = w.id_warehouse');
        if ($id_product) {
            $query->where('wpl.id_product = ' . (int) $id_product);
        }
        if ($id_product_attribute) {
            $query->where('wpl.id_product_attribute = ' . (int) $id_product_attribute);
        }
        $query->orderBy('w.reference ASC');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
    }

    /**
     * For a given $id_warehouse, returns its name.
     *
     * @param int $id_warehouse Warehouse Id
     *
     * @return string Name
     */
    public static function getWarehouseNameById($id_warehouse)
    {
        $query = new DbQuery();
        $query->select('name');
        $query->from('warehouse');
        $query->where('id_warehouse = ' . (int) $id_warehouse);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * For a given pack, returns the warehouse it can be shipped from.
     *
     * @param int $id_product
     *
     * @return array|bool id_warehouse or false
     */
    public static function getPackWarehouses($id_product, $id_shop = null)
    {
        if (!Pack::isPack($id_product)) {
            return false;
        }

        if (null === $id_shop) {
            $id_shop = Context::getContext()->shop->id;
        }

        // warehouses of the pack
        $pack_warehouses = WarehouseProductLocation::getCollection((int) $id_product);

        // array with all warehouses id to check
        $list = [];

        // fills $list
        foreach ($pack_warehouses as $pack_warehouse) {
            /* @var WarehouseProductLocation $pack_warehouse */
            $list['pack_warehouses'][] = (int) $pack_warehouse->id_warehouse;
        }

        $res = false;
        // returns final list
        if (!empty($list)) {
            $res = call_user_func_array('array_intersect', $list);
        }

        return $res;
    }

    /**
     * @deprecated Since 9.0 and will be removed in 10.0
     */
    public function resetStockAvailable()
    {
        @trigger_error(sprintf(
            '%s is deprecated since 9.0 and will be removed in 10.0.',
            __METHOD__
        ), E_USER_DEPRECATED);

        return true;
    }

    /*********************************\
     *
     * Webservices Specific Methods
     *
     *********************************/

    /**
     * Webservice : gets the value of the warehouse.
     *
     * @return float
     */
    public function getWsStockValue()
    {
        return $this->getStockValue();
    }

    /**
     * Webservice : gets the ids stock associated to this warehouse.
     *
     * @return array
     */
    public function getWsStocks()
    {
        $query = new DbQuery();
        $query->select('s.id_stock as id');
        $query->from('stock', 's');
        $query->where('s.id_warehouse =' . (int) $this->id);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
    }

    /**
     * Webservice : gets the ids shops associated to this warehouse.
     *
     * @return array
     */
    public function getWsShops()
    {
        $query = new DbQuery();
        $query->select('ws.id_shop as id, s.name');
        $query->from('warehouse_shop', 'ws');
        $query->leftJoin('shop', 's', 's.id_shop = ws.id_shop');
        $query->where($this->def['primary'] . ' = ' . (int) $this->id);

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

        return $res;
    }

    /**
     * Webservice : gets the ids carriers associated to this warehouse.
     *
     * @return array
     */
    public function getWsCarriers()
    {
        $ids_carrier = [];

        $query = new DbQuery();
        $query->select('wc.id_carrier as id');
        $query->from('warehouse_carrier', 'wc');
        $query->where($this->def['primary'] . ' = ' . (int) $this->id);

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

        if (!is_array($res)) {
            return $ids_carrier;
        }

        foreach ($res as $carriers) {
            foreach ($carriers as $carrier) {
                $ids_carrier[] = $carrier;
            }
        }

        return $ids_carrier;
    }
}
