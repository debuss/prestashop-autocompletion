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

namespace PrestaShopBundle\Install;

use tools\profiling\Db;
use PrestashopInstallerException;

class SqlLoader
{
    /**
     * @var Db
     */
    protected $db;

    /**
     * @var array List of keywords which will be replaced in queries
     */
    protected $metadata = [];

    /**
     * @var array List of errors during last parsing
     */
    protected $errors = [];

    /**
     * @param Db|null $db
     */
    public function __construct(?Db $db = null)
    {
        if (null === $db) {
            $db = Db::getInstance();
        }
        $this->db = $db;
    }

    /**
     * Set a list of keywords which will be replaced in queries.
     *
     * @param array $data
     */
    public function setMetaData(array $data)
    {
        foreach ($data as $k => $v) {
            $this->metadata[$k] = $v;
        }
    }

    /**
     * Parse a SQL file and execute queries.
     *
     * @deprecated use parseFile()
     *
     * @param string $filename
     * @param bool $stop_when_fail
     */
    public function parse_file($filename, $stop_when_fail = true)
    {
        return $this->parseFile($filename, $stop_when_fail);
    }

    /**
     * Parse a SQL file and execute queries.
     *
     * @param string $filename
     * @param bool $stop_when_fail
     */
    public function parseFile($filename, $stop_when_fail = true)
    {
        if (!file_exists($filename)) {
            throw new PrestashopInstallerException("File $filename not found");
        }

        return $this->parse(file_get_contents($filename), $stop_when_fail);
    }

    /**
     * Parse and execute a list of SQL queries.
     *
     * @param string $content
     * @param bool $stop_when_fail
     */
    public function parse($content, $stop_when_fail = true)
    {
        $this->errors = [];

        $content = str_replace(array_keys($this->metadata), array_values($this->metadata), $content);
        $queries = preg_split('#;\s*[\r\n]+#', $content);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!$query) {
                continue;
            }

            if (!$this->db->execute($query)) {
                $this->errors[] = [
                    'errno' => $this->db->getNumberError(),
                    'error' => $this->db->getMsgError(),
                    'query' => $query,
                ];

                if ($stop_when_fail) {
                    return false;
                }
            }
        }

        return count($this->errors) ? false : true;
    }

    /**
     * Get list of errors from last parsing.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
