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
use tools\profiling\Tools;

/**
 * ProductSupplierCore class.
 *
 * @since 1.5.0
 */
class ProductSupplierCore extends ObjectModel
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
     * @var int the supplier ID
     * */
    public $id_supplier;

    /**
     * @var string The supplier reference of the product
     * */
    public $product_supplier_reference;

    /**
     * @var int the currency ID for unit price tax excluded
     * */
    public $id_currency;

    /**
     * @var float The unit price tax excluded of the product
     * */
    public $product_supplier_price_te;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'product_supplier',
        'primary' => 'id_product_supplier',
        'fields' => [
            'product_supplier_reference' => ['type' => self::TYPE_STRING, 'validate' => 'isReference', 'size' => 64],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_supplier' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'product_supplier_price_te' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice'],
            'id_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
        ],
    ];

    /**
     * @see ObjectModel::$webserviceParameters
     */
    protected $webserviceParameters = [
        'objectsNodeName' => 'product_suppliers',
        'objectNodeName' => 'product_supplier',
        'fields' => [
            'id_product' => ['xlink_resource' => 'products'],
            'id_product_attribute' => ['xlink_resource' => 'combinations'],
            'id_supplier' => ['xlink_resource' => 'suppliers'],
            'id_currency' => ['xlink_resource' => 'currencies'],
        ],
    ];

    /**
     * For a given product and supplier, gets the product supplier reference.
     *
     * @param int $idProduct Product ID
     * @param int $idProductAttribute Product Attribute ID
     * @param int $idSupplier Supplier ID
     *
     * @return string|false Product Supplier reference
     */
    public static function getProductSupplierReference($idProduct, $idProductAttribute, $idSupplier)
    {
        // build query
        $query = new DbQuery();
        $query->select('ps.product_supplier_reference');
        $query->from('product_supplier', 'ps');
        $query->where(
            'ps.id_product = ' . (int) $idProduct . '
			AND ps.id_product_attribute = ' . (int) $idProductAttribute . '
			AND ps.id_supplier = ' . (int) $idSupplier
        );

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * For a given product and supplier, gets the product supplier unit price.
     *
     * @param int $idProduct Product ID
     * @param int $idProductAttribute Product Attribute ID
     * @param int $idSupplier Supplier ID
     * @param bool $withCurrency Optional With currency
     *
     * @return string|array
     */
    public static function getProductSupplierPrice($idProduct, $idProductAttribute, $idSupplier, $withCurrency = false)
    {
        // build query
        $query = new DbQuery();
        $query->select('ps.product_supplier_price_te');
        if ($withCurrency) {
            $query->select('ps.id_currency');
        }
        $query->from('product_supplier', 'ps');
        $query->where(
            'ps.id_product = ' . (int) $idProduct . '
			AND ps.id_product_attribute = ' . (int) $idProductAttribute . '
			AND ps.id_supplier = ' . (int) $idSupplier
        );

        if (!$withCurrency) {
            return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
        }

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        if (isset($res[0])) {
            return $res[0];
        }

        return $res;
    }

    /**
     * For a given product and supplier, gets corresponding ProductSupplier ID.
     *
     * @param int $idProduct
     * @param int $idProductAttribute
     * @param int $idSupplier
     *
     * @return int
     */
    public static function getIdByProductAndSupplier($idProduct, $idProductAttribute, $idSupplier)
    {
        $query = new DbQuery();
        $query->select('ps.id_product_supplier');
        $query->from('product_supplier', 'ps');
        $query->where(
            'ps.id_product = ' . (int) $idProduct . '
			AND ps.id_product_attribute = ' . (int) $idProductAttribute . '
			AND ps.id_supplier = ' . (int) $idSupplier
        );

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * For a given product, retrieves its suppliers.
     *
     * @param int $idProduct
     * @param bool $groupBySupplier
     *
     * @return PrestaShopCollection Collection of ProductSupplier
     */
    public static function getSupplierCollection($idProduct, $groupBySupplier = true)
    {
        $suppliers = new PrestaShopCollection('ProductSupplier');
        $suppliers->where('id_product', '=', (int) $idProduct);

        if ($groupBySupplier) {
            $suppliers->groupBy('id_supplier');
        }

        return $suppliers;
    }

    /**
     * For a given Supplier, Product, returns the purchased price.
     *
     * @param int|null $idSupplier
     * @param int|null $idProduct
     * @param int $idProductAttribute Optional
     * @param bool $convertedPrice Optional
     *
     * @return float|null
     */
    public static function getProductPrice($idSupplier, $idProduct, $idProductAttribute = 0, $convertedPrice = false)
    {
        if (null === $idSupplier || null === $idProduct) {
            return null;
        }

        $query = new DbQuery();
        $query->select('product_supplier_price_te as price_te, id_currency');
        $query->from('product_supplier');
        $query->where('id_product = ' . (int) $idProduct . ' AND id_product_attribute = ' . (int) $idProductAttribute);
        $query->where('id_supplier = ' . (int) $idSupplier);

        $row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($query);
        if (empty($row)) {
            return null;
        }

        if ($convertedPrice) {
            return Tools::convertPrice($row['price_te'], $row['id_currency']);
        }

        return $row['price_te'];
    }

    /**
     * For a given product and supplier, gets the product supplier datas.
     *
     * @param int $idProduct Product ID
     * @param int $idProductAttribute Product Attribute ID
     * @param int $idSupplier Supplier ID
     *
     * @return array
     */
    public static function getProductSupplierData($idProduct, $idProductAttribute, $idSupplier)
    {
        // build query
        $query = new DbQuery();
        $query->select('ps.product_supplier_reference, ps.product_supplier_price_te as price, ps.id_currency');
        $query->from('product_supplier', 'ps');
        $query->where(
            'ps.id_product = ' . (int) $idProduct . '
			AND ps.id_product_attribute = ' . (int) $idProductAttribute . '
			AND ps.id_supplier = ' . (int) $idSupplier
        );

        $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
        if (isset($res[0])) {
            return $res[0];
        }

        return $res;
    }
}
