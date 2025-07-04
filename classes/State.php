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
 * Class StateCore.
 */
class StateCore extends ObjectModel
{
    /** @var int Country id which state belongs */
    public $id_country;

    /** @var int Zone id which state belongs */
    public $id_zone;

    /** @var string 2 letters iso code */
    public $iso_code;

    /** @var string Name */
    public $name;

    /** @var bool Status for delivery */
    public $active = true;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'state',
        'primary' => 'id_state',
        'fields' => [
            'id_country' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_zone' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'iso_code' => ['type' => self::TYPE_STRING, 'validate' => 'isStateIsoCode', 'required' => true, 'size' => 7],
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 80],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
        ],
    ];

    protected $webserviceParameters = [
        'fields' => [
            'id_zone' => ['xlink_resource' => 'zones'],
            'id_country' => ['xlink_resource' => 'countries'],
        ],
    ];

    public static function getStates($idLang = false, $active = false)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
		SELECT `id_state`, `id_country`, `id_zone`, `iso_code`, `name`, `active`
		FROM `' . _DB_PREFIX_ . 'state`
		' . ($active ? 'WHERE active = 1' : '') . '
		ORDER BY `name` ASC');
    }

    /**
     * Get a state name with its ID.
     *
     * @param int $idState Country ID
     *
     * @return bool|string State name
     */
    public static function getNameById($idState)
    {
        if (!$idState) {
            return false;
        }
        $cacheId = 'State::getNameById_' . (int) $idState;
        if (!Cache::isStored($cacheId)) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                '
				SELECT `name`
				FROM `' . _DB_PREFIX_ . 'state`
				WHERE `id_state` = ' . (int) $idState
            );
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Get State ID with its name.
     *
     * @param string $state State ID
     *
     * @return bool|int state id
     */
    public static function getIdByName($state)
    {
        if (empty($state)) {
            return false;
        }
        $cacheId = 'State::getIdByName_' . pSQL($state);
        if (!Cache::isStored($cacheId)) {
            $result = (int) Db::getInstance()->getValue('
				SELECT `id_state`
				FROM `' . _DB_PREFIX_ . 'state`
				WHERE `name` = \'' . pSQL($state) . '\'
			');
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    /**
     * Get a state id with its iso code.
     *
     * @param string $isoCode Iso code
     * @param int|null $idCountry
     *
     * @return int state id
     */
    public static function getIdByIso($isoCode, $idCountry = null)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT `id_state`
            FROM `' . _DB_PREFIX_ . 'state`
            WHERE `iso_code` = \'' . pSQL($isoCode) . '\'
            ' . ($idCountry ? 'AND `id_country` = ' . (int) $idCountry : '')
        );
    }

    /**
     * Delete a state only if is not in use.
     *
     * @return bool
     */
    public function delete()
    {
        if (!$this->isUsed()) {
            // Database deletion
            $result = Db::getInstance()->delete($this->def['table'], '`' . $this->def['primary'] . '` = ' . (int) $this->id);
            if (!$result) {
                return false;
            }

            // Database deletion for multilingual fields related to the object
            if (!empty($this->def['multilang'])) {
                Db::getInstance()->delete(bqSQL($this->def['table']) . '_lang', '`' . $this->def['primary'] . '` = ' . (int) $this->id);
            }

            return $result;
        } else {
            return false;
        }
    }

    /**
     * Check if a state is used.
     *
     * @return bool
     */
    public function isUsed()
    {
        return $this->countUsed() > 0;
    }

    /**
     * Returns the number of utilisation of a state.
     *
     * @return int count for this state
     */
    public function countUsed()
    {
        return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*)
			FROM `' . _DB_PREFIX_ . 'address`
			WHERE `' . $this->def['primary'] . '` = ' . (int) $this->id
        );
    }

    /**
     * Get states by Country ID.
     *
     * @param int $idCountry Country ID
     * @param bool $active true if the state must be active
     * @param string $orderBy order by field
     * @param string $sort sort key (ASC or DESC)
     *
     * @return array|false|mysqli_result|PDOStatement|resource|null
     */
    public static function getStatesByIdCountry($idCountry, $active = false, $orderBy = null, $sort = 'ASC')
    {
        if (empty($idCountry)) {
            throw new PrestaShopException('Country ID is invalid.');
        }

        $available_sort = ['DESC', 'ASC', 'asc', 'desc'];

        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('state', 's');
        $sql->where('s.id_country = ' . (int) $idCountry . ($active ? ' AND s.active = 1' : ''));

        if (array_key_exists($orderBy, static::$definition['fields'])) {
            $sort = trim($sort);
            if (in_array($sort, $available_sort)) {
                $orderBy = $orderBy . ' ' . $sort;
            }
            $sql->orderBy($orderBy);
        }

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get Zone ID.
     *
     * @param int $idState State ID
     *
     * @return false|string|null
     */
    public static function getIdZone($idState)
    {
        if (!Validate::isUnsignedId($idState)) {
            throw new PrestaShopException('State ID is invalid.');
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            '
			SELECT `id_zone`
			FROM `' . _DB_PREFIX_ . 'state`
			WHERE `id_state` = ' . (int) $idState
        );
    }

    /**
     * @param array $idsStates State IDs
     * @param int $idZone Zone ID
     *
     * @return bool
     */
    public function affectZoneToSelection($idsStates, $idZone)
    {
        // cast every array values to int (security)
        $idsStates = array_map('intval', $idsStates);

        return Db::getInstance()->execute('
		UPDATE `' . _DB_PREFIX_ . 'state` SET `id_zone` = ' . (int) $idZone . ' WHERE `id_state` IN (' . implode(',', $idsStates) . ')
		');
    }
}
