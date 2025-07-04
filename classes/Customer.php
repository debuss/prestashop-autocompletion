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
use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\InvalidShopConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\Form\Admin\Type\FormattedTextareaType;
use tools\profiling\Db;
use tools\profiling\Hook;
use tools\profiling\ObjectModel;
use tools\profiling\Tools;

/***
 * Class CustomerCore
 */
class CustomerCore extends ObjectModel
{
    /** @var int Customer ID */
    public $id;

    /** @var int Shop ID */
    public $id_shop;

    /** @var int ShopGroup ID */
    public $id_shop_group;

    /** @var string Secure key */
    public $secure_key;

    /** @var string protected note */
    public $note;

    /** @var int Gender ID */
    public $id_gender = 0;

    /** @var int Default group ID */
    public $id_default_group;

    /** @var int Current language used by the customer */
    public $id_lang;

    /** @var string Lastname */
    public $lastname;

    /** @var string Firstname */
    public $firstname;

    /** @var string Birthday (yyyy-mm-dd) */
    public $birthday = null;

    /** @var string e-mail */
    public $email;

    /** @var bool Newsletter subscription */
    public $newsletter;

    /** @var string Newsletter ip registration */
    public $ip_registration_newsletter;

    /** @var string Newsletter registration date */
    public $newsletter_date_add;

    /** @var bool Opt-in subscription */
    public $optin;

    /** @var string WebSite * */
    public $website;

    /** @var string Company */
    public $company;

    /** @var string SIRET */
    public $siret;

    /** @var string APE */
    public $ape;

    /** @var float Outstanding allow amount (B2B opt) */
    public $outstanding_allow_amount = 0;

    /** @var int Show public prices (B2B opt) */
    public $show_public_prices = 0;

    /** @var int Risk ID (B2B opt) */
    public $id_risk;

    /** @var int Max payment day */
    public $max_payment_days = 0;

    /** @var string Password */
    public $passwd;

    /** @var string Datetime Password */
    public $last_passwd_gen;

    /** @var bool Status */
    public $active = true;

    /** @var bool Status */
    public $is_guest = false;

    /** @var bool True if carrier has been deleted (staying in database as deleted) */
    public $deleted = false;

    /** @var string|null Object creation date */
    public $date_add;

    /** @var string Object last modification date */
    public $date_upd;

    public $years;
    public $days;
    public $months;

    /** @var int customer id_country as determined by geolocation */
    public $geoloc_id_country;
    /** @var int customer id_state as determined by geolocation */
    public $geoloc_id_state;
    /** @var string customer postcode as determined by geolocation */
    public $geoloc_postcode;

    /** @var bool is the customer logged in */
    public $logged = false;

    /** @var int id_guest meaning the guest table, not the guest customer */
    public $id_guest;

    public $groupBox;

    /** @var string|null Unique token for forgot password feature */
    public $reset_password_token;

    /** @var string|null token validity date for forgot password feature */
    public $reset_password_validity;

    protected $webserviceParameters = [
        'objectMethods' => [
            'add' => 'addWs',
            'update' => 'updateWs',
        ],
        'fields' => [
            'id_default_group' => ['xlink_resource' => 'groups'],
            'id_lang' => ['xlink_resource' => 'languages'],
            'newsletter_date_add' => [],
            'ip_registration_newsletter' => [],
            'last_passwd_gen' => ['setter' => false],
            'secure_key' => ['setter' => false],
            'deleted' => [],
            'passwd' => ['setter' => 'setWsPasswd'],
        ],
        'associations' => [
            'groups' => ['resource' => 'group'],
        ],
    ];

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'customer',
        'primary' => 'id_customer',
        'fields' => [
            'secure_key' => ['type' => self::TYPE_STRING, 'validate' => 'isMd5', 'copy_post' => false, 'size' => 32],
            'lastname' => ['type' => self::TYPE_STRING, 'validate' => 'isCustomerName', 'required' => true, 'size' => 255],
            'firstname' => ['type' => self::TYPE_STRING, 'validate' => 'isCustomerName', 'required' => true, 'size' => 255],
            'email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'required' => true, 'size' => 255],
            'passwd' => ['type' => self::TYPE_STRING, 'validate' => 'isHashedPassword', 'required' => true, 'size' => 255],
            'last_passwd_gen' => ['type' => self::TYPE_STRING, 'copy_post' => false],
            'id_gender' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'birthday' => ['type' => self::TYPE_DATE, 'validate' => 'isBirthDate'],
            'newsletter' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'newsletter_date_add' => ['type' => self::TYPE_DATE, 'copy_post' => false],
            'ip_registration_newsletter' => ['type' => self::TYPE_STRING, 'copy_post' => false, 'size' => 15],
            'optin' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'website' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'size' => 128],
            'company' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
            'siret' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 14],
            'ape' => ['type' => self::TYPE_STRING, 'validate' => 'isApe', 'size' => 6],
            'outstanding_allow_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'copy_post' => false],
            'show_public_prices' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'copy_post' => false],
            'id_risk' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'copy_post' => false],
            'max_payment_days' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'copy_post' => false],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'copy_post' => false],
            'deleted' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'copy_post' => false],
            'note' => ['type' => self::TYPE_HTML, 'size' => FormattedTextareaType::LIMIT_MEDIUMTEXT_UTF8_MB4, 'copy_post' => false],
            'is_guest' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'copy_post' => false],
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'copy_post' => false],
            'id_shop_group' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'copy_post' => false],
            'id_default_group' => ['type' => self::TYPE_INT, 'copy_post' => false],
            'id_lang' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'copy_post' => false],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'copy_post' => false],
            'reset_password_token' => ['type' => self::TYPE_STRING, 'validate' => 'isSha1', 'size' => 40, 'copy_post' => false],
            'reset_password_validity' => ['type' => self::TYPE_DATE, 'validate' => 'isDateOrNull', 'copy_post' => false],
        ],
    ];

    protected static $_defaultGroupId = [];
    protected static $_customerHasAddress = [];
    protected static $_customer_groups = [];

    /**
     * CustomerCore constructor.
     *
     * @param int|null $id
     */
    public function __construct($id = null)
    {
        // It sets default value for customer group even when customer does not exist
        $this->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        parent::__construct($id);
    }

    /**
     * Adds current Customer as a new Object to the database.
     *
     * @param bool $autoDate Automatically set `date_upd` and `date_add` columns
     * @param bool $nullValues Whether we want to use NULL values instead of empty quotes values
     *
     * @return bool Indicates whether the Customer has been successfully added
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function add($autoDate = true, $nullValues = true)
    {
        $this->id_shop = ($this->id_shop) ? $this->id_shop : Context::getContext()->shop->id;
        $this->id_shop_group = ($this->id_shop_group) ? $this->id_shop_group : Context::getContext()->shop->id_shop_group;
        $this->id_lang = ($this->id_lang) ? $this->id_lang : Context::getContext()->language->id;
        $this->birthday = (empty($this->years) ? $this->birthday : (int) $this->years . '-' . (int) $this->months . '-' . (int) $this->days);
        $this->secure_key = md5(uniqid((string) mt_rand(0, mt_getrandmax()), true));
        $this->last_passwd_gen = date('Y-m-d H:i:s', strtotime('-' . Configuration::get('PS_PASSWD_TIME_FRONT') . 'minutes'));

        if ($this->newsletter && !Validate::isDate($this->newsletter_date_add)) {
            $this->newsletter_date_add = date('Y-m-d H:i:s');
        }

        // Set default group of the customer depending on prestashop configuration
        if ($this->id_default_group == Configuration::get('PS_CUSTOMER_GROUP')) {
            if ($this->is_guest) {
                $this->id_default_group = (int) Configuration::get('PS_GUEST_GROUP');
            } else {
                $this->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
            }
        }

        /* Can't create a guest customer, if this feature is disabled */
        if ($this->is_guest && !Configuration::get('PS_GUEST_CHECKOUT_ENABLED')) {
            return false;
        }
        $success = parent::add($autoDate, $nullValues);

        // Update the group assignments themselves
        $this->updateGroup($this->groupBox);

        return $success;
    }

    /**
     * Adds current Customer as a new Object to the database.
     *
     * @param bool $autodate Automatically set `date_upd` and `date_add` columns
     * @param bool $null_values Whether we want to use NULL values instead of empty quotes values
     *
     * @return bool Indicates whether the Customer has been successfully added
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function addWs($autodate = true, $null_values = false)
    {
        // Check if registered customer exists with the email we are trying to add
        if (!$this->isGuest() && Customer::customerExists($this->email)) {
            WebserviceRequest::getInstance()->setError(
                500,
                $this->trans(
                    'The email is already used, please choose another one',
                    [],
                    'Admin.Notifications.Error'
                ),
                140
            );

            return false;
        }

        return $this->add($autodate, $null_values);
    }

    /**
     * Updates the current Customer in the database.
     *
     * @param bool $nullValues Whether we want to use NULL values instead of empty quotes values
     *
     * @return bool Indicates whether the Customer has been successfully updated
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function update($nullValues = false)
    {
        $this->birthday = (empty($this->years) ? $this->birthday : (int) $this->years . '-' . (int) $this->months . '-' . (int) $this->days);

        if ($this->newsletter && !Validate::isDate($this->newsletter_date_add)) {
            $this->newsletter_date_add = date('Y-m-d H:i:s');
        }

        if ($this->deleted) {
            $addresses = $this->getAddresses((int) Configuration::get('PS_LANG_DEFAULT'));
            foreach ($addresses as $address) {
                $obj = new Address((int) $address['id_address']);
                $obj->deleted = true;
                $obj->save();
            }
        }

        return parent::update(true);
    }

    /**
     * Updates the current Customer in the database.
     *
     * @param bool $nullValues Whether we want to use NULL values instead of empty quotes values
     *
     * @return bool Indicates whether the Customer has been successfully updated
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function updateWs($nullValues = false)
    {
        // Check if registered customer exists with the email we are trying to add.
        // Also check if the customer found is a different customer than our object.
        $customerExists = (int) Customer::customerExists($this->email, true);
        if (!$this->isGuest() && $customerExists > 0 && $customerExists !== (int) $this->id) {
            WebserviceRequest::getInstance()->setError(
                500,
                $this->trans(
                    'The email is already used, please choose another one',
                    [],
                    'Admin.Notifications.Error'
                ),
                141
            );

            return false;
        }

        return $this->update($nullValues = false);
    }

    /**
     * Deletes current Customer from the database.
     *
     * @return bool True if delete was successful
     *
     * @throws PrestaShopException
     */
    public function delete()
    {
        if (!count(Order::getCustomerOrders((int) $this->id))) {
            $addresses = $this->getAddresses((int) Configuration::get('PS_LANG_DEFAULT'));
            foreach ($addresses as $address) {
                $obj = new Address((int) $address['id_address']);
                $obj->delete();
            }
        }
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'customer_group` WHERE `id_customer` = ' . (int) $this->id);
        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'message WHERE id_customer=' . (int) $this->id);
        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'specific_price WHERE id_customer=' . (int) $this->id);

        $carts = Db::getInstance()->executeS('SELECT id_cart FROM ' . _DB_PREFIX_ . 'cart WHERE id_customer=' . (int) $this->id . ' AND id_cart NOT IN (SELECT id_cart FROM `' . _DB_PREFIX_ . 'orders`)');
        if ($carts) {
            foreach ($carts as $cart) {
                Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart=' . (int) $cart['id_cart']);
                Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'cart_product WHERE id_cart=' . (int) $cart['id_cart']);
            }
        }

        $cts = Db::getInstance()->executeS('SELECT id_customer_thread FROM ' . _DB_PREFIX_ . 'customer_thread WHERE id_customer=' . (int) $this->id);
        if ($cts) {
            foreach ($cts as $ct) {
                Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'customer_thread WHERE id_customer_thread=' . (int) $ct['id_customer_thread']);
                Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'customer_message WHERE id_customer_thread=' . (int) $ct['id_customer_thread']);
            }
        }

        CartRule::deleteByIdCustomer((int) $this->id);

        return parent::delete();
    }

    /**
     * Return customers list.
     *
     * @param bool|null $onlyActive Returns only active customers when `true`
     *
     * @return array Customers
     */
    public static function getCustomers($onlyActive = null)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
            SELECT `id_customer`, `email`, `firstname`, `lastname`
            FROM `' . _DB_PREFIX_ . 'customer`
            WHERE 1 ' . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER) .
            ($onlyActive ? ' AND `active` = 1' : '') . '
            ORDER BY `id_customer` ASC'
        );
    }

    /**
     * Return customer instance from its e-mail (optionally check password).
     *
     * @param string $email e-mail
     * @param string $plaintextPassword Password is also checked if specified
     * @param bool $ignoreGuest to ignore guest customers
     *
     * @return bool|Customer|CustomerCore Customer instance
     *
     * @throws InvalidArgumentException if given input is not valid
     */
    public function getByEmail($email, $plaintextPassword = null, $ignoreGuest = true)
    {
        if (!Validate::isEmail($email)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot get customer by email as %s is not a valid email',
                $email
            ));
        }

        $shopGroup = Shop::getGroupFromShop(Shop::getContextShopID(), false);

        $sql = new DbQuery();
        $sql->select('c.`passwd`');
        $sql->from('customer', 'c');
        $sql->where('c.`email` = \'' . pSQL($email) . '\'');
        if (Shop::getContext() == Shop::CONTEXT_SHOP && $shopGroup['share_customer']) {
            $sql->where('c.`id_shop_group` = ' . (int) Shop::getContextShopGroupID());
        } else {
            $sql->where('c.`id_shop` IN (' . implode(', ', Shop::getContextListShopID(Shop::SHARE_CUSTOMER)) . ')');
        }

        if ($ignoreGuest) {
            $sql->where('c.`is_guest` = 0');
        }
        $sql->where('c.`deleted` = 0');

        $passwordHash = Db::getInstance()->getValue($sql);

        try {
            /** @var PrestaShop\PrestaShop\Core\Crypto\Hashing $crypto */
            $crypto = ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');
        } catch (CoreException $e) {
            return false;
        }

        $shouldCheckPassword = null !== $plaintextPassword;
        if ($shouldCheckPassword && !$crypto->checkHash($plaintextPassword, $passwordHash)) {
            return false;
        }

        $sql = new DbQuery();
        $sql->select('c.*');
        $sql->from('customer', 'c');
        $sql->where('c.`email` = \'' . pSQL($email) . '\'');
        if (Shop::getContext() == Shop::CONTEXT_SHOP && $shopGroup['share_customer']) {
            $sql->where('c.`id_shop_group` = ' . (int) Shop::getContextShopGroupID());
        } else {
            $sql->where('c.`id_shop` IN (' . implode(', ', Shop::getContextListShopID(Shop::SHARE_CUSTOMER)) . ')');
        }
        if ($ignoreGuest) {
            $sql->where('c.`is_guest` = 0');
        }
        $sql->where('c.`deleted` = 0');

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        if (!$result) {
            return false;
        }

        $this->id = $result['id_customer'];
        foreach ($result as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        if ($shouldCheckPassword && !$crypto->isFirstHash($plaintextPassword, $passwordHash)) {
            $this->passwd = $crypto->hash($plaintextPassword);
            $this->update();
        }

        return $this;
    }

    /**
     * Retrieve customers by email address.
     *
     * @param string $email
     *
     * @return array Customers
     */
    public static function getCustomersByEmail($email)
    {
        $sql = 'SELECT *
                FROM `' . _DB_PREFIX_ . 'customer`
                WHERE `email` = \'' . pSQL($email) . '\'
                    ' . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER);

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Check id the customer is active or not.
     *
     * @param int $idCustomer
     *
     * @return bool Customer validity
     */
    public static function isBanned($idCustomer)
    {
        if (!Validate::isUnsignedId($idCustomer)) {
            return true;
        }
        $cacheId = 'Customer::isBanned_' . (int) $idCustomer;
        if (!Cache::isStored($cacheId)) {
            $result = (bool) !Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
            SELECT `id_customer`
            FROM `' . _DB_PREFIX_ . 'customer`
            WHERE `id_customer` = \'' . (int) $idCustomer . '\'
            AND active = 1
            AND `deleted` = 0');
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Check if e-mail is already registered in database.
     *
     * @param string $email e-mail
     * @param bool $returnId If true the method returns the Customer ID, or boolean
     * @param bool $ignoreGuest to ignore guest customers
     *
     * @return bool|int Customer ID if found
     *                  `false` otherwise
     */
    public static function customerExists($email, $returnId = false, $ignoreGuest = true)
    {
        if (!Validate::isEmail($email)) {
            return false;
        }

        $result = Db::getInstance()->getValue('
        SELECT `id_customer`
        FROM `' . _DB_PREFIX_ . 'customer`
        WHERE `email` = \'' . pSQL($email) . '\'
        ' . Shop::addSqlRestriction(Shop::SHARE_CUSTOMER) . '
        ' . ($ignoreGuest ? ' AND `is_guest` = 0' : ''), false);

        return $returnId ? (int) $result : (bool) $result;
    }

    /**
     * Check if an address is owned by a customer.
     *
     * @param int $idCustomer Customer ID
     * @param int $idAddress Address ID
     *
     * @return bool result
     */
    public static function customerHasAddress($idCustomer, $idAddress)
    {
        $key = (int) $idCustomer . '-' . (int) $idAddress;
        if (!array_key_exists($key, self::$_customerHasAddress)) {
            self::$_customerHasAddress[$key] = (bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
            SELECT `id_address`
            FROM `' . _DB_PREFIX_ . 'address`
            WHERE `id_customer` = ' . (int) $idCustomer . '
            AND `id_address` = ' . (int) $idAddress . '
            AND `deleted` = 0');
        }

        return self::$_customerHasAddress[$key];
    }

    public static function resetStaticCache()
    {
        self::$_customerHasAddress = [];
        self::$_customer_groups = [];
        self::$_defaultGroupId = [];
    }

    /**
     * Reset Address cache.
     *
     * @param int $idCustomer Customer ID
     * @param int $idAddress Address ID
     */
    public static function resetAddressCache($idCustomer = null, $idAddress = null)
    {
        if ($idCustomer === null || $idAddress === null) {
            self::$_customerHasAddress = [];
            self::$_customer_groups = [];
            self::$_defaultGroupId = [];
        }
        $key = (int) $idCustomer . '-' . (int) $idAddress;
        if (array_key_exists($key, self::$_customerHasAddress)) {
            unset(self::$_customerHasAddress[$key]);
        }
    }

    /**
     * Return customer addresses.
     *
     * @param int $idLang Language ID
     *
     * @return array Addresses
     */
    public function getAddresses($idLang)
    {
        $group = Context::getContext()->shop->getGroup();
        $shareOrder = isset($group->share_order) ? (bool) $group->share_order : false;
        $cacheId = 'Customer::getAddresses'
            . '-' . (int) $this->id
            . '-' . (int) $idLang
            . '-' . ($shareOrder ? 1 : 0);
        if (!Cache::isStored($cacheId)) {
            $sql = 'SELECT DISTINCT a.*, cl.`name` AS country, s.name AS state, s.iso_code AS state_iso
                    FROM `' . _DB_PREFIX_ . 'address` a
                    LEFT JOIN `' . _DB_PREFIX_ . 'country` c ON (a.`id_country` = c.`id_country`)
                    LEFT JOIN `' . _DB_PREFIX_ . 'country_lang` cl ON (c.`id_country` = cl.`id_country`)
                    LEFT JOIN `' . _DB_PREFIX_ . 'state` s ON (s.`id_state` = a.`id_state`)
                    ' . ($shareOrder ? '' : Shop::addSqlAssociation('country', 'c')) . '
                    WHERE `id_lang` = ' . (int) $idLang . ' AND `id_customer` = ' . (int) $this->id . ' AND a.`deleted` = 0';

            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Get simplified Addresses arrays.
     *
     * @param int|null $idLang Language ID
     *
     * @return array
     */
    public function getSimpleAddresses($idLang = null)
    {
        if (!$this->id) {
            return [];
        }

        if (null === $idLang) {
            $idLang = Context::getContext()->language->id;
        }

        $sql = $this->getSimpleAddressSql(null, $idLang);
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
        $addresses = [];
        foreach ($result as $addr) {
            $addresses[$addr['id']] = $addr;
        }

        return $addresses;
    }

    /**
     * Get Address as array.
     *
     * @param int $idAddress Address ID
     * @param int|null $idLang Language ID
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public function getSimpleAddress($idAddress, $idLang = null)
    {
        if (!$this->id || !(int) $idAddress || !$idAddress) {
            return [
                'id' => '',
                'alias' => '',
                'firstname' => '',
                'lastname' => '',
                'company' => '',
                'address1' => '',
                'address2' => '',
                'postcode' => '',
                'city' => '',
                'id_state' => '',
                'state' => '',
                'state_iso' => '',
                'id_country' => '',
                'country' => '',
                'country_iso' => '',
                'other' => '',
                'phone' => '',
                'phone_mobile' => '',
                'vat_number' => '',
                'dni' => '',
            ];
        }

        $sql = $this->getSimpleAddressSql($idAddress, $idLang);
        $res = Db::getInstance()->executeS($sql);
        if (count($res) === 1) {
            return $res[0];
        } else {
            return $res;
        }
    }

    /**
     * Get SQL query to retrieve Address in an array.
     *
     * @param int|null $idAddress Address ID
     * @param int|null $idLang Language ID
     *
     * @return string
     */
    public function getSimpleAddressSql($idAddress = null, $idLang = null)
    {
        if (null === $idLang) {
            $idLang = Context::getContext()->language->id;
        }
        $shareOrder = (bool) Context::getContext()->shop->getGroup()->share_order;

        $sql = 'SELECT DISTINCT
                      a.`id_address` AS `id`,
                      a.`alias`,
                      a.`firstname`,
                      a.`lastname`,
                      a.`company`,
                      a.`address1`,
                      a.`address2`,
                      a.`postcode`,
                      a.`city`,
                      a.`id_state`,
                      s.name AS state,
                      s.`iso_code` AS state_iso,
                      a.`id_country`,
                      cl.`name` AS country,
                      co.`iso_code` AS country_iso,
                      a.`other`,
                      a.`phone`,
                      a.`phone_mobile`,
                      a.`vat_number`,
                      a.`dni`
                    FROM `' . _DB_PREFIX_ . 'address` a
                    LEFT JOIN `' . _DB_PREFIX_ . 'country` co ON (a.`id_country` = co.`id_country`)
                    LEFT JOIN `' . _DB_PREFIX_ . 'country_lang` cl ON (co.`id_country` = cl.`id_country`)
                    LEFT JOIN `' . _DB_PREFIX_ . 'state` s ON (s.`id_state` = a.`id_state`)
                    ' . ($shareOrder ? '' : Shop::addSqlAssociation('country', 'co')) . '
                    WHERE
                        `id_lang` = ' . (int) $idLang . '
                        AND `id_customer` = ' . (int) $this->id . '
                        AND a.`deleted` = 0
                        AND a.`active` = 1';

        if (null !== $idAddress) {
            $sql .= ' AND a.`id_address` = ' . (int) $idAddress;
        }

        $sql .= ' ORDER BY a.`alias`';

        return $sql;
    }

    /**
     * Count the number of addresses for a customer.
     *
     * @param int $idCustomer Customer ID
     *
     * @return int Number of addresses
     */
    public static function getAddressesTotalById($idCustomer)
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(`id_address`)
            FROM `' . _DB_PREFIX_ . 'address`
            WHERE `id_customer` = ' . (int) $idCustomer . '
            AND `deleted` = 0'
        );
    }

    /**
     * Check if customer password is the right one.
     *
     * @param int $idCustomer Customer ID
     * @param string $passwordHash Hashed password
     *
     * @return bool result
     */
    public static function checkPassword($idCustomer, $passwordHash)
    {
        if (!Validate::isUnsignedId($idCustomer)) {
            throw new PrestaShopException('Customer ID is invalid.');
        }

        // Check that customers password hasn't changed since last login
        $context = Context::getContext();
        if ($passwordHash != $context->cookie->__get('passwd')) {
            return false;
        }

        $cacheId = 'Customer::checkPassword' . (int) $idCustomer . '-' . $passwordHash;
        if (!Cache::isStored($cacheId)) {
            $sql = new DbQuery();
            $sql->select('c.`id_customer`');
            $sql->from('customer', 'c');
            $sql->where('c.`id_customer` = ' . (int) $idCustomer);
            $sql->where('c.`passwd` = \'' . pSQL($passwordHash) . '\'');

            $result = (bool) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);

            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Light back office search for customers.
     *
     * @param string $query Searched string
     * @param int|null $limit Limit query results
     * @param ShopConstraint|null $shopConstraint provide specific shop constraint or else it will use context shops for search
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null Corresponding customers
     *
     * @throws PrestaShopDatabaseException
     */
    public static function searchByName($query, $limit = null, ?ShopConstraint $shopConstraint = null)
    {
        $sql = 'SELECT c.*,
                GROUP_CONCAT(cg.id_group SEPARATOR \',\') AS group_ids
                FROM `' . _DB_PREFIX_ . 'customer` c
                LEFT JOIN `' . _DB_PREFIX_ . 'customer_group` cg ON c.id_customer = cg.id_customer
                WHERE 1';

        if ($shopConstraint) {
            if ($shopConstraint->getShopGroupId()) {
                throw new InvalidShopConstraintException('Shop group constraint is not supported');
            }

            if ($shopConstraint->getShopId()) {
                // filter by shop_id if its not all shops constraint
                $sql .= sprintf(' AND c.id_shop = %d', $shopConstraint->getShopId()->getValue());
            }
        }

        $search_items = explode(' ', $query);
        $research_fields = ['c.id_customer', 'c.firstname', 'c.lastname', 'c.email'];
        if (Configuration::get('PS_B2B_ENABLE')) {
            $research_fields[] = 'c.company';
        }

        $items = [];
        foreach ($research_fields as $field) {
            foreach ($search_items as $item) {
                $items[$item][] = $field . ' LIKE \'%' . pSQL($item) . '%\' ';
            }
        }

        foreach ($items as $likes) {
            $sql .= ' AND (' . implode(' OR ', $likes) . ') ';
        }

        if (!$shopConstraint) {
            // this is for backwards compatibility, it uses shop context if specific shopConstraint is not provided
            $sql .= Shop::addSqlRestriction(Shop::SHARE_CUSTOMER);
        }

        $sql .= ' GROUP BY c.id_customer ';

        if ($limit) {
            $sql .= ' LIMIT 0, ' . (int) $limit;
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Search for customers by ip address.
     *
     * @param string $ip Searched string
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public static function searchByIp($ip)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
        SELECT DISTINCT c.*
        FROM `' . _DB_PREFIX_ . 'customer` c
        LEFT JOIN `' . _DB_PREFIX_ . 'guest` g ON g.id_customer = c.id_customer
        LEFT JOIN `' . _DB_PREFIX_ . 'connections` co ON g.id_guest = co.id_guest
        WHERE co.`ip_address` = \'' . (int) ip2long(trim($ip)) . '\'');
    }

    /**
     * Return several useful statistics about customer.
     *
     * @return array Stats
     */
    public function getStats()
    {
        $result = Db::getInstance()->getRow('
        SELECT COUNT(`id_order`) AS nb_orders, SUM(`total_paid` / o.`conversion_rate`) AS total_orders
        FROM `' . _DB_PREFIX_ . 'orders` o
        WHERE o.`id_customer` = ' . (int) $this->id . '
        AND o.valid = 1');

        $result2 = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
        SELECT c.`date_add` AS last_visit
        FROM `' . _DB_PREFIX_ . 'connections` c
        LEFT JOIN `' . _DB_PREFIX_ . 'guest` g USING (id_guest)
        WHERE g.`id_customer` = ' . (int) $this->id . ' ORDER BY c.`date_add` DESC ');

        $result3 = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
        SELECT (YEAR(CURRENT_DATE)-YEAR(c.`birthday`)) - (RIGHT(CURRENT_DATE, 5)<RIGHT(c.`birthday`, 5)) AS age
        FROM `' . _DB_PREFIX_ . 'customer` c
        WHERE c.`id_customer` = ' . (int) $this->id);

        $result['last_visit'] = $result2['last_visit'] ?? null;
        $result['age'] = (isset($result3['age']) && $result3['age'] != date('Y') ? $result3['age'] : '--');

        return $result;
    }

    /**
     * Get last 10 emails sent to the Customer.
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public function getLastEmails()
    {
        if (!$this->id) {
            return [];
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
        SELECT m.*, l.name as language
        FROM `' . _DB_PREFIX_ . 'mail` m
        LEFT JOIN `' . _DB_PREFIX_ . 'lang` l ON m.id_lang = l.id_lang
        WHERE `recipient` = "' . pSQL($this->email) . '"
        ORDER BY m.date_add DESC
        LIMIT 10');
    }

    /**
     * Get last 10 Connections of the Customer.
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public function getLastConnections()
    {
        if (!$this->id) {
            return [];
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            '
            SELECT c.id_connections, c.date_add, COUNT(cp.id_page) AS pages, TIMEDIFF(MAX(cp.time_end), c.date_add) as time, http_referer,INET_NTOA(ip_address) as ipaddress
            FROM `' . _DB_PREFIX_ . 'guest` g
            LEFT JOIN `' . _DB_PREFIX_ . 'connections` c ON c.id_guest = g.id_guest
            LEFT JOIN `' . _DB_PREFIX_ . 'connections_page` cp ON c.id_connections = cp.id_connections
            WHERE g.`id_customer` = ' . (int) $this->id . '
            GROUP BY c.`id_connections`
            ORDER BY c.date_add DESC
            LIMIT 10'
        );
    }

    /**
     * Check if Customer ID exists.
     *
     * @param int $idCustomer Customer ID
     *
     * @return int|null Customer ID if found
     */
    public static function customerIdExistsStatic($idCustomer)
    {
        $cacheId = 'Customer::customerIdExistsStatic' . (int) $idCustomer;
        if (!Cache::isStored($cacheId)) {
            $result = (int) Db::getInstance()->getValue('
            SELECT `id_customer`
            FROM ' . _DB_PREFIX_ . 'customer c
            WHERE c.`id_customer` = ' . (int) $idCustomer);
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Update customer groups associated to the object.
     *
     * @param array $list groups
     */
    public function updateGroup($list)
    {
        Hook::exec('actionCustomerBeforeUpdateGroup', ['id_customer' => $this->id, 'groups' => $list]);

        // If some groups are provided, respect this. If not, automatically add the default group of the customer
        if (!empty($list)) {
            $this->cleanGroups();
            $this->addGroups($list);
        } else {
            $this->addGroups([$this->id_default_group]);
        }
    }

    /**
     * Remove this Customer ID from Customer Groups.
     *
     * @return bool Indicates whether the Customer ID has been successfully removed
     *              from the Customer Group Db table
     */
    public function cleanGroups()
    {
        return Db::getInstance()->delete('customer_group', 'id_customer = ' . (int) $this->id);
    }

    /**
     * Add the Customer to the given Customer Groups.
     *
     * @param array $groups Customer Group IDs
     */
    public function addGroups($groups)
    {
        Hook::exec('actionCustomerAddGroups', ['id_customer' => $this->id, 'groups' => $groups]);
        foreach ($groups as $group) {
            $row = ['id_customer' => (int) $this->id, 'id_group' => (int) $group];
            Db::getInstance()->insert('customer_group', $row, false, true, Db::INSERT_IGNORE);
        }
    }

    /**
     * Get Groups that have the given Customer ID.
     *
     * @param int $idCustomer Customer ID
     *
     * @return array|mixed
     */
    public static function getGroupsStatic($idCustomer)
    {
        if (!Group::isFeatureActive()) {
            return [Configuration::get('PS_CUSTOMER_GROUP')];
        }

        if ($idCustomer == 0) {
            self::$_customer_groups[$idCustomer] = [(int) Configuration::get('PS_UNIDENTIFIED_GROUP')];
        }

        if (!isset(self::$_customer_groups[$idCustomer])) {
            self::$_customer_groups[$idCustomer] = [];
            $result = Db::getInstance()->executeS('
            SELECT cg.`id_group`
            FROM ' . _DB_PREFIX_ . 'customer_group cg
            WHERE cg.`id_customer` = ' . (int) $idCustomer);
            foreach ($result as $group) {
                self::$_customer_groups[$idCustomer][] = (int) $group['id_group'];
            }
        }

        return self::$_customer_groups[$idCustomer];
    }

    /**
     * Get Groups of this Customer
     *
     * @return array|mixed
     */
    public function getGroups()
    {
        return Customer::getGroupsStatic((int) $this->id);
    }

    /**
     * Get Products bought by this Customer.
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public function getBoughtProducts()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
        SELECT * FROM `' . _DB_PREFIX_ . 'orders` o
        LEFT JOIN `' . _DB_PREFIX_ . 'order_detail` od ON o.id_order = od.id_order
        WHERE o.valid = 1 AND o.`id_customer` = ' . (int) $this->id);
    }

    /**
     * Get Default Customer Group ID.
     *
     * @param int $idCustomer Customer ID
     *
     * @return mixed|string|null
     */
    public static function getDefaultGroupId($idCustomer)
    {
        if (!Group::isFeatureActive()) {
            static $psCustomerGroup = null;
            if ($psCustomerGroup === null) {
                $psCustomerGroup = Configuration::get('PS_CUSTOMER_GROUP');
            }

            return $psCustomerGroup;
        }

        if (!isset(self::$_defaultGroupId[(int) $idCustomer])) {
            self::$_defaultGroupId[(int) $idCustomer] = Db::getInstance()->getValue(
                '
                SELECT `id_default_group`
                FROM `' . _DB_PREFIX_ . 'customer`
                WHERE `id_customer` = ' . (int) $idCustomer
            );
        }

        return self::$_defaultGroupId[(int) $idCustomer];
    }

    /**
     * Get current country or default country
     *
     * @param int $idCustomer
     * @param Cart|null $cart
     *
     * @return int Country ID
     */
    public static function getCurrentCountry($idCustomer, ?Cart $cart = null)
    {
        if (!$cart) {
            $cart = Context::getContext()->cart;
        }
        if (!$cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}) {
            $idAddress = (int) Db::getInstance()->getValue(sprintf(
                'SELECT `id_address` FROM `%saddress` WHERE `id_customer` = %d AND `deleted` = 0 ORDER BY `id_address`',
                _DB_PREFIX_,
                (int) $idCustomer
            ));
        } else {
            $idAddress = $cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
        }
        $ids = Address::getCountryAndState($idAddress);

        return (int) ($ids['id_country'] ?? Configuration::get('PS_COUNTRY_DEFAULT'));
    }

    /**
     * Is the current Customer a Guest?
     *
     * @return bool Indicates whether the Customer is a Guest
     */
    public function isGuest()
    {
        return (bool) $this->is_guest;
    }

    /**
     * Transform the Guest to a Customer.
     *
     * @param int $idLang Language ID
     * @param string|null $password Password
     *
     * @return bool Indicates if a process has been successful
     */
    public function transformToCustomer($idLang, $password = null)
    {
        // If it's not a guest, wrong call
        if (!$this->isGuest()) {
            return false;
        }

        // If a customer with the same email already exists, wrong call
        if (Customer::customerExists($this->email)) {
            return false;
        }

        $this->is_guest = false;

        /** @var PrestaShop\PrestaShop\Core\Crypto\Hashing $crypto */
        $crypto = ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');

        /*
        * If this is an anonymous conversion and we want the customer to set his own password,
        * we set a random one for now. If a password was provided, we check it's validity.
        */
        if (empty($password)) {
            $this->passwd = $crypto->hash(Tools::passwdGen(16, 'RANDOM'));
        } else {
            if (!Validate::isAcceptablePasswordLength($password) || !Validate::isAcceptablePasswordScore($password)) {
                return false;
            }
            $this->passwd = $crypto->hash($password);
        }

        /*
        * Now, we need to update his group. The guest should have had a PS_GUEST_GROUP previously, but if
        * not, no biggie, it's gonna be fixed now.
        *
        * We will remove all entries from customer_group table and add a customer group from configuration.
        * We also need to set it as his default group.
        */
        $this->cleanGroups();
        $this->addGroups([Configuration::get('PS_CUSTOMER_GROUP')]);
        $this->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        $this->stampResetPasswordToken();

        if (!$this->update()) {
            return false;
        }

        // If it's an anonymous conversion, we send him a link to set his new password.
        // Otherwise, just a welcome email, if configured.
        if (empty($password)) {
            $this->sendWelcomeEmail($idLang, true);
        } elseif (Configuration::get('PS_CUSTOMER_CREATION_EMAIL')) {
            $this->sendWelcomeEmail($idLang);
        }

        return true;
    }

    /**
     * Sends an informational email to the customer, to notify him that
     * his account was created.
     *
     * This email can optionally contain a link to set his new password.
     *
     * @param int $idLang Language ID to send the email in
     * @param bool $sendPasswordLink Should a template with a password reset link be used
     *
     * @return bool If the mail was sent successfully
     */
    public function sendWelcomeEmail(int $idLang, bool $sendPasswordLink = false)
    {
        // Use provided lang ID, or take the one from context
        $language = new Language($idLang);
        if (!Validate::isLoadedObject($language)) {
            $language = Context::getContext()->language;
        }

        // Build basic email variables
        $template = 'account';
        $subject = Context::getContext()->getTranslator()->trans(
            'Welcome!',
            [],
            'Emails.Subject',
            $language->locale
        );
        $vars = [
            '{firstname}' => $this->firstname,
            '{lastname}' => $this->lastname,
            '{email}' => $this->email,
        ];

        // If we are also sending a link to password, we will alter the template,
        // change subject and add password URL to variables.
        if ($sendPasswordLink) {
            $template = 'guest_to_customer';
            $subject = Context::getContext()->getTranslator()->trans(
                'Your guest account has been transformed into a customer account',
                [],
                'Emails.Subject',
                $language->locale
            );
            $vars['{url}'] = Context::getContext()->link->getPageLink(
                'password',
                null,
                null,
                sprintf(
                    'token=%s&id_customer=%s&reset_token=%s',
                    $this->secure_key,
                    (int) $this->id,
                    $this->reset_password_token
                )
            );
        }

        return Mail::Send(
            (int) $idLang,
            $template,
            $subject,
            $vars,
            $this->email,
            $this->firstname . ' ' . $this->lastname,
            null,
            null,
            null,
            null,
            _PS_MAIL_DIR_,
            false,
            (int) $this->id_shop
        );
    }

    /**
     * Set password
     * (for webservice).
     *
     * @param string $passwd Password
     *
     * @return bool Indictes whether the password has been successfully set
     */
    public function setWsPasswd($passwd)
    {
        /** @var PrestaShop\PrestaShop\Core\Crypto\Hashing $crypto */
        $crypto = ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');
        if ($this->id == 0 || $this->passwd != $passwd) {
            $this->passwd = $crypto->hash($passwd);
        }

        return true;
    }

    /**
     * Check customer information and return customer validity.
     *
     * @since 1.5.0
     *
     * @param bool $withGuest
     *
     * @return bool customer validity
     */
    public function isLogged($withGuest = false)
    {
        if (!$withGuest && $this->is_guest == 1) {
            return false;
        }

        /* Customer is valid only if it can be load and if object password is the same as database one */
        return
            $this->logged == true
            && $this->id
            && Validate::isUnsignedId($this->id)
            && Customer::checkPassword($this->id, $this->passwd)
            && Context::getContext()->cookie->isSessionAlive()
        ;
    }

    /**
     * Logout.
     *
     * @since 1.5.0
     */
    public function logout()
    {
        Hook::exec('actionCustomerLogoutBefore', ['customer' => $this]);

        // Cookie class will handle complete destroying of the cookie and sending it out to the client
        if (isset(Context::getContext()->cookie)) {
            Context::getContext()->cookie->logout();
        }

        $this->logged = false;

        Hook::exec('actionCustomerLogoutAfter', ['customer' => $this]);
    }

    /**
     * Soft logout, delete everything that links to the customer
     * but leave there affiliate's information.
     *
     * @since 1.5.0
     */
    public function mylogout()
    {
        Hook::exec('actionCustomerLogoutBefore', ['customer' => $this]);

        // Cookie class will remove all customer information from the cookie and update that cookie
        if (isset(Context::getContext()->cookie)) {
            Context::getContext()->cookie->mylogout();
        }

        $this->logged = false;

        Hook::exec('actionCustomerLogoutAfter', ['customer' => $this]);
    }

    /**
     * Get last empty Cart for this Customer, when last cart is not empty return false.
     *
     * @param bool|true $withOrder Only return a Cart that have been converted into an Order
     *
     * @return bool|int
     */
    public function getLastEmptyCart($withOrder = true)
    {
        $carts = Cart::getCustomerCarts((int) $this->id, $withOrder);
        if (!count($carts)) {
            return false;
        }
        $cart = array_shift($carts);
        $cart = new Cart((int) $cart['id_cart']);

        return $cart->nbProducts() === 0 ? (int) $cart->id : false;
    }

    /**
     * Get outstanding amount.
     *
     * @return float Outstanding amount
     */
    public function getOutstanding()
    {
        $query = new DbQuery();
        $query->select('SUM(oi.total_paid_tax_incl)');
        $query->from('order_invoice', 'oi');
        $query->leftJoin('orders', 'o', 'oi.id_order = o.id_order');
        $query->groupBy('o.id_customer');
        $query->where('o.id_customer = ' . (int) $this->id);
        $totalPaid = (float) Db::getInstance()->getValue($query->build());

        $query = new DbQuery();
        $query->select('SUM(op.amount)');
        $query->from('order_payment', 'op');
        $query->leftJoin('order_invoice_payment', 'oip', 'op.id_order_payment = oip.id_order_payment');
        $query->leftJoin('orders', 'o', 'oip.id_order = o.id_order');
        $query->groupBy('o.id_customer');
        $query->where('o.id_customer = ' . (int) $this->id);
        $totalRest = (float) Db::getInstance()->getValue($query->build());

        return $totalPaid - $totalRest;
    }

    /**
     * Get Customer Groups
     * (for webservice).
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public function getWsGroups()
    {
        return Db::getInstance()->executeS(
            '
            SELECT cg.`id_group` as id
            FROM ' . _DB_PREFIX_ . 'customer_group cg
            ' . Shop::addSqlAssociation('group', 'cg') . '
            WHERE cg.`id_customer` = ' . (int) $this->id
        );
    }

    /**
     * Set Customer Groups
     * (for webservice).
     *
     * @param array $result
     *
     * @return bool
     */
    public function setWsGroups($result)
    {
        $groups = [];
        foreach ($result as $row) {
            $groups[] = $row['id'];
        }
        $this->cleanGroups();
        $this->addGroups($groups);

        return true;
    }

    /**
     * @see ObjectModel::getWebserviceObjectList()
     */
    public function getWebserviceObjectList($sqlJoin, $sqlFilter, $sqlSort, $sqlLimit)
    {
        $sqlFilter .= Shop::addSqlRestriction(Shop::SHARE_CUSTOMER, 'main');

        return parent::getWebserviceObjectList($sqlJoin, $sqlFilter, $sqlSort, $sqlLimit);
    }

    /**
     * Fill Reset password unique token with random sha1 and its validity date. For forgot password feature.
     */
    public function stampResetPasswordToken()
    {
        $salt = $this->id . '-' . $this->secure_key;
        $this->reset_password_token = sha1(time() . $salt);
        $validity = (int) Configuration::get('PS_PASSWD_RESET_VALIDITY') ?: 1440;
        $this->reset_password_validity = date('Y-m-d H:i:s', strtotime('+' . $validity . ' minutes'));
    }

    /**
     * Test if a reset password token is present and is recent enough to avoid creating a new one (in case of customer triggering the forgot password link too often).
     */
    public function hasRecentResetPasswordToken()
    {
        if (!$this->reset_password_token) {
            return false;
        }

        // TODO maybe use another 'recent' value for this test. For instance, equals password validity value.
        if (!$this->reset_password_validity || strtotime($this->reset_password_validity) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Returns the valid reset password token if it validity date is > now().
     */
    public function getValidResetPasswordToken()
    {
        if (!$this->reset_password_token) {
            return false;
        }

        if (!$this->reset_password_validity || strtotime($this->reset_password_validity) < time()) {
            return false;
        }

        return $this->reset_password_token;
    }

    /**
     * Delete reset password token data.
     */
    public function removeResetPasswordToken()
    {
        $this->reset_password_token = null;
        $this->reset_password_validity = null;
    }
}
