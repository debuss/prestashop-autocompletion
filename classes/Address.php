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

use PrestaShopBundle\Form\Admin\Type\FormattedTextareaType;
use tools\profiling\Db;
use tools\profiling\Hook;
use tools\profiling\ObjectModel;
use tools\profiling\Tools;

/**
 * Class AddressCore.
 */
class AddressCore extends ObjectModel
{
    /** @var int Customer ID which address belongs to */
    public $id_customer = null;

    /** @var int Manufacturer ID which address belongs to */
    public $id_manufacturer = null;

    /** @var int Supplier ID which address belongs to */
    public $id_supplier = null;

    /** @var int Id warehouse the address belongs to
     *
     * @deprecated since 9.0, advanced stock management has been completely removed
     */
    public $id_warehouse = 0;

    /** @var int Country ID */
    public $id_country;

    /** @var int State ID */
    public $id_state;

    /** @var string Country name */
    public $country;

    /** @var string Alias (eg. Home, Work...) */
    public $alias;

    /** @var string Company (optional) */
    public $company;

    /** @var string Lastname */
    public $lastname;

    /** @var string Firstname */
    public $firstname;

    /** @var string Address first line */
    public $address1;

    /** @var string Address second line (optional) */
    public $address2;

    /** @var string Postal code */
    public $postcode;

    /** @var string City */
    public $city;

    /** @var string Any other useful information */
    public $other;

    /** @var string Phone number */
    public $phone;

    /** @var string Mobile phone number */
    public $phone_mobile;

    /** @var string VAT number */
    public $vat_number;

    /** @var string DNI number */
    public $dni;

    /** @var string Object creation date */
    public $date_add;

    /** @var string Object last modification date */
    public $date_upd;

    /** @var bool True if address has been deleted (staying in database as deleted) */
    public $deleted = false;

    /** @var int|null */
    public $id_address;

    /** @var array Zone IDs cache */
    protected static $_idZones = [];

    /** @var array Country IDs cache */
    protected static $_idCountries = [];

    /** @var array<int, bool> Store if an adress ID exists. Please note that soft-deleted address also belongs to this cache. */
    protected static $addressExists = [];

    /**
     * @see ObjectModel::$definition
     */

    // when you override this class, do not create a field with allow_null=>true
    // because it will give you exception on checkout address step
    public static $definition = [
        'table' => 'address',
        'primary' => 'id_address',
        'fields' => [
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId', 'copy_post' => false],
            'id_manufacturer' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId', 'copy_post' => false],
            'id_supplier' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId', 'copy_post' => false],
            'id_warehouse' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId', 'copy_post' => false],
            'id_country' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_state' => ['type' => self::TYPE_INT, 'validate' => 'isNullOrUnsignedId'],
            'alias' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 32],
            'company' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
            'lastname' => ['type' => self::TYPE_STRING, 'validate' => 'isName', 'required' => true, 'size' => 255],
            'firstname' => ['type' => self::TYPE_STRING, 'validate' => 'isName', 'required' => true, 'size' => 255],
            'vat_number' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32],
            'address1' => ['type' => self::TYPE_STRING, 'validate' => 'isAddress', 'required' => true, 'size' => 128],
            'address2' => ['type' => self::TYPE_STRING, 'validate' => 'isAddress', 'size' => 128],
            'postcode' => ['type' => self::TYPE_STRING, 'validate' => 'isPostCode', 'size' => 12],
            'city' => ['type' => self::TYPE_STRING, 'validate' => 'isCityName', 'required' => true, 'size' => 64],
            'other' => ['type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => FormattedTextareaType::LIMIT_MEDIUMTEXT_UTF8_MB4],
            'phone' => ['type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 32],
            'phone_mobile' => ['type' => self::TYPE_STRING, 'validate' => 'isPhoneNumber', 'size' => 32],
            'dni' => ['type' => self::TYPE_STRING, 'validate' => 'isDniLite', 'size' => 16],
            'deleted' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'copy_post' => false],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false],
        ],
    ];

    /** @var array Web service parameters */
    protected $webserviceParameters = [
        'objectsNodeName' => 'addresses',
        'fields' => [
            'id_customer' => ['xlink_resource' => 'customers'],
            'id_manufacturer' => ['xlink_resource' => 'manufacturers'],
            'id_supplier' => ['xlink_resource' => 'suppliers'],
            'id_country' => ['xlink_resource' => 'countries'],
            'id_state' => ['xlink_resource' => 'states'],
        ],
    ];

    /**
     * Build an Address.
     *
     * @param int|null $id_address Existing Address ID in order to load object (optional)
     * @param int|null $id_lang Language ID (optional). Configuration::PS_LANG_DEFAULT will be used if null
     */
    public function __construct($id_address = null, $id_lang = null)
    {
        parent::__construct($id_address);

        /* Get and cache address country name */
        if ($this->id) {
            $this->country = Country::getNameById($id_lang ? $id_lang : Configuration::get('PS_LANG_DEFAULT'), $this->id_country);
        }
    }

    /**
     * reset static cache (eg unit testing purpose).
     */
    public static function resetStaticCache()
    {
        static::$_idZones = [];
        static::$_idCountries = [];
        static::$addressExists = [];
    }

    /**
     * @see ObjectModel::add()
     */
    public function add($autodate = true, $null_values = false)
    {
        if (!parent::add($autodate, $null_values)) {
            return false;
        }

        if (Validate::isUnsignedId($this->id_customer)) {
            Customer::resetAddressCache($this->id_customer, $this->id);
        }

        // Update the cache
        static::$addressExists[$this->id] = true;

        return true;
    }

    /**
     * @see ObjectModel::update()
     */
    public function update($null_values = false)
    {
        // Empty related caches
        if (isset(self::$_idCountries[$this->id])) {
            unset(self::$_idCountries[$this->id]);
        }
        if (isset(self::$_idZones[$this->id])) {
            unset(self::$_idZones[$this->id]);
        }

        // Update the cache
        // This is probably not correct, because it should be true only if the address is NOT flagged as deleted
        static::$addressExists[$this->id] = true;

        if (Validate::isUnsignedId($this->id_customer)) {
            Customer::resetAddressCache($this->id_customer, $this->id);
        }

        /* Skip the required fields */
        if ($this->isUsed()) {
            self::$fieldsRequiredDatabase['Address'] = [];
        }

        return parent::update($null_values);
    }

    /**
     * @see ObjectModel::delete()
     */
    public function delete()
    {
        if (Validate::isUnsignedId($this->id_customer)) {
            Customer::resetAddressCache($this->id_customer, $this->id);
        }

        /*
         * Deleting an address can go two ways.
         *
         * 1) If the address is used in an order, we will only soft-delete it. This means mark it with a flag,
         *    hide it everywhere and prevent anyone using it. We must absolutely retain all the business data
         *    for the order.
         * 2) If it's not used, we can safely delete the address.
         */

        // First step is to unlink this address from all NON-ORDERED carts.
        $this->deleteCartAddress();

        // Second step - check if the address has been used in some order.
        if (!$this->isUsed()) {
            // If NO, we can safely delete it.
            if (isset(static::$addressExists[$this->id])) {
                static::$addressExists[$this->id] = false;
            }

            return parent::delete();
        } else {
            // If YES, we only soft delete it and keep it in the database.
            return $this->softDelete();
        }
    }

    /**
     * Removes the address from all non ordered carts using it,
     * to avoid errors on not existing address.
     */
    protected function deleteCartAddress()
    {
        // Reset it from all delivery addresses
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'cart c
            LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON c.id_cart = o.id_cart
            SET c.id_address_delivery = 0
            WHERE c.id_address_delivery = ' . $this->id . ' AND o.id_order IS NULL';
        Db::getInstance()->execute($sql);

        // Reset it from all invoice addresses
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'cart c
            LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON c.id_cart = o.id_cart
            SET c.id_address_invoice = 0
            WHERE c.id_address_invoice = ' . $this->id . ' AND o.id_order IS NULL';
        Db::getInstance()->execute($sql);
    }

    /**
     * Get Zone ID for a given address.
     *
     * @param int $id_address Address ID for which we want to get the Zone ID
     *
     * @return int|bool Zone ID
     */
    public static function getZoneById($id_address)
    {
        if (empty($id_address)) {
            return false;
        }

        if (isset(self::$_idZones[$id_address])) {
            return self::$_idZones[$id_address];
        }

        $id_zone = Hook::exec('actionGetIDZoneByAddressID', ['id_address' => $id_address]);

        if (is_numeric($id_zone)) {
            self::$_idZones[$id_address] = (int) $id_zone;

            return self::$_idZones[$id_address];
        }

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT s.`id_zone` AS id_zone_state, c.`id_zone`
			FROM `' . _DB_PREFIX_ . 'address` a
			LEFT JOIN `' . _DB_PREFIX_ . 'country` c ON c.`id_country` = a.`id_country`
			LEFT JOIN `' . _DB_PREFIX_ . 'state` s ON s.`id_state` = a.`id_state`
			WHERE a.`id_address` = ' . (int) $id_address);

        if (empty($result['id_zone_state']) && empty($result['id_zone'])) {
            return false;
        }

        self::$_idZones[$id_address] = !empty($result['id_zone_state'])
            ? (int) $result['id_zone_state']
            : (int) $result['id_zone'];

        return self::$_idZones[$id_address];
    }

    /**
     * Check if the Country is active for a given address.
     *
     * @param int $id_address Address ID for which we want to get the Country status
     *
     * @return int|bool Country status
     */
    public static function isCountryActiveById($id_address)
    {
        if (empty($id_address)) {
            return false;
        }

        $cache_id = 'Address::isCountryActiveById_' . (int) $id_address;
        if (!Cache::isStored($cache_id)) {
            $result = (bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
			SELECT c.`active`
			FROM `' . _DB_PREFIX_ . 'address` a
			LEFT JOIN `' . _DB_PREFIX_ . 'country` c ON c.`id_country` = a.`id_country`
			WHERE a.`id_address` = ' . (int) $id_address);
            Cache::store($cache_id, $result);

            return $result;
        }

        return Cache::retrieve($cache_id);
    }

    /**
     * {@inheritdoc}
     */
    public function validateField($field, $value, $id_lang = null, $skip = [], $human_errors = false)
    {
        $error = parent::validateField($field, $value, $id_lang, $skip, $human_errors);
        if (true !== $error || 'dni' !== $field) {
            return $error;
        }

        // Special validation for dni, check if the country needs it
        if (!$this->deleted && static::dniRequired((int) $this->id_country) && Tools::isEmpty($value)) {
            if ($human_errors) {
                return $this->trans(
                    'The %s field is required.',
                    [$this->displayFieldName($field, get_class($this))],
                    'Admin.Notifications.Error'
                );
            }

            return $this->trans(
                'Property %s is empty.',
                [get_class($this) . '->' . htmlspecialchars($field)],
                'Admin.Notifications.Error'
            );
        }

        return true;
    }

    /**
     * Request to check if DNI field is required
     * depending on the current selected country.
     *
     * @param int $idCountry
     *
     * @return bool
     */
    public static function dniRequired($idCountry)
    {
        return (bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT c.`need_identification_number` ' .
            'FROM `' . _DB_PREFIX_ . 'country` c ' .
            'WHERE c.`id_country` = ' . (int) $idCountry
        );
    }

    /**
     * Check if Address is used (at least one order placed).
     *
     * @return int|bool Order count for this Address
     */
    public function isUsed()
    {
        if ((int) $this->id <= 0) {
            return false;
        }

        $result = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
		SELECT COUNT(`id_order`) AS used
		FROM `' . _DB_PREFIX_ . 'orders`
		WHERE `id_address_delivery` = ' . (int) $this->id . '
		OR `id_address_invoice` = ' . (int) $this->id);

        return $result > 0 ? (int) $result : false;
    }

    /**
     * Get Country and State of this Address.
     *
     * @param int $id_address Address ID
     *
     * @return array|bool
     */
    public static function getCountryAndState($id_address)
    {
        if (isset(self::$_idCountries[$id_address])) {
            return self::$_idCountries[$id_address];
        }
        if ($id_address) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT `id_country`, `id_state`, `vat_number`, `postcode` FROM `' . _DB_PREFIX_ . 'address`
			WHERE `id_address` = ' . (int) $id_address);
        } else {
            $result = false;
        }
        self::$_idCountries[$id_address] = $result;

        return $result;
    }

    /**
     * Specify if an address is already in database.
     * Please note that a soft-deleted address also counts as existing.
     *
     * @param int $id_address Address id
     * @param bool $useCache Use Cache for optimizing queries
     *
     * @return bool The address exists
     */
    public static function addressExists($id_address, bool $useCache = false)
    {
        if ((int) $id_address <= 0) {
            return false;
        }

        if ($useCache && isset(static::$addressExists[$id_address])) {
            return static::$addressExists[$id_address];
        }

        static::$addressExists[$id_address] = (bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `id_address`
            FROM ' . _DB_PREFIX_ . 'address a
            WHERE a.`id_address` = ' . (int) $id_address,
            false
        );

        return static::$addressExists[$id_address];
    }

    /**
     * Check if the address is valid.
     *
     * @param int $id_address Address id
     *
     * @return bool The address is valid
     */
    public static function isValid($id_address)
    {
        $id_address = (int) $id_address;
        $isValid = Db::getInstance()->getValue('
            SELECT `id_address` FROM ' . _DB_PREFIX_ . 'address a
            WHERE a.`id_address` = ' . $id_address . ' AND a.`deleted` = 0 AND a.`active` = 1
        ');

        return (bool) $isValid;
    }

    /**
     * Get the first address id of the customer.
     *
     * @param int $id_customer Customer id
     * @param bool $active Active addresses only
     *
     * @return bool|int|null
     */
    public static function getFirstCustomerAddressId($id_customer, $active = true)
    {
        if (!$id_customer) {
            return false;
        }
        $cache_id = 'Address::getFirstCustomerAddressId_' . (int) $id_customer . '-' . (bool) $active;
        if (!Cache::isStored($cache_id)) {
            $result = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                '
				SELECT `id_address`
				FROM `' . _DB_PREFIX_ . 'address`
				WHERE `id_customer` = ' . (int) $id_customer . ' AND `deleted` = 0' . ($active ? ' AND `active` = 1' : '')
            );
            Cache::store($cache_id, $result);

            return $result;
        }

        return Cache::retrieve($cache_id);
    }

    /**
     * Initialize an address corresponding to the specified id address or if empty to the
     * default shop configuration.
     *
     * @param int $id_address
     * @param bool $with_geoloc
     *
     * @return Address address
     *
     * @throws PrestaShopException
     */
    public static function initialize($id_address = null, $with_geoloc = false)
    {
        $context = Context::getContext();

        if ($id_address) {
            $context_hash = (int) $id_address;
        } elseif ($with_geoloc && isset($context->customer->geoloc_id_country)) {
            $context_hash = md5((int) $context->customer->geoloc_id_country . '-' . (int) $context->customer->id_state . '-' .
                                $context->customer->postcode);
        } else {
            $context_hash = md5((string) $context->country->id);
        }

        $cache_id = 'Address::initialize_' . $context_hash;

        if (!Cache::isStored($cache_id)) {
            // if an id_address has been specified retrieve the address
            if ($id_address) {
                $address = new Address((int) $id_address);

                if (!Validate::isLoadedObject($address)) {
                    throw new PrestaShopException('Invalid address #' . (int) $id_address);
                }
            } elseif ($with_geoloc && isset($context->customer->geoloc_id_country)) {
                $address = new Address();
                $address->id_country = (int) $context->customer->geoloc_id_country;
                $address->id_state = (int) $context->customer->id_state;
                $address->postcode = $context->customer->postcode;
            } elseif ((int) $context->country->id && ((int) $context->country->id != Configuration::get('PS_SHOP_COUNTRY_ID'))) {
                $address = new Address();
                $address->id_country = (int) $context->country->id;
                $address->id_state = 0;
                $address->postcode = '0';
            } elseif ((int) Configuration::get('PS_SHOP_COUNTRY_ID')) {
                // set the default address
                $address = new Address();
                $address->id_country = (int) Configuration::get('PS_SHOP_COUNTRY_ID');
                $address->id_state = (int) Configuration::get('PS_SHOP_STATE_ID');
                $address->postcode = Configuration::get('PS_SHOP_CODE');
            } else {
                // set the default address
                $address = new Address();
                $address->id_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');
                $address->id_state = 0;
                $address->postcode = '0';
            }
            Cache::store($cache_id, $address);

            return $address;
        }

        return Cache::retrieve($cache_id);
    }

    /**
     * Returns Address ID for a given Supplier ID.
     *
     * @since 1.5.0
     *
     * @param int $id_supplier Supplier ID
     *
     * @return int $id_address Address ID
     */
    public static function getAddressIdBySupplierId($id_supplier)
    {
        $query = new DbQuery();
        $query->select('id_address');
        $query->from('address');
        $query->where('id_supplier = ' . (int) $id_supplier);
        $query->where('deleted = 0');
        $query->where('id_customer = 0');
        $query->where('id_manufacturer = 0');

        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * Check if the alias already exists.
     *
     * @param string $alias Alias of an address
     * @param int $id_address Address id
     * @param int $id_customer Customer id
     *
     * @return false|string|null Amount of aliases found
     *
     * @todo: Find out if we shouldn't be returning an int instead? (breaking change)
     */
    public static function aliasExist($alias, $id_address, $id_customer)
    {
        $query = new DbQuery();
        $query->select('count(*)');
        $query->from('address');
        $query->where('alias = \'' . pSQL($alias) . '\'');
        $query->where('id_address != ' . (int) $id_address);
        $query->where('id_customer = ' . (int) $id_customer);
        $query->where('deleted = 0');

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query, false);
    }

    /**
     * @see ObjectModel::getFieldsRequiredDB();
     */
    public function getFieldsRequiredDB()
    {
        return parent::getCachedFieldsRequiredDatabase();
    }
}
