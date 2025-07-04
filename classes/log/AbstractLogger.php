<?php

use tools\profiling\Hook;

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
abstract class AbstractLoggerCore implements PrestaShopLoggerInterface
{
    public $level;
    protected $level_value = [
        0 => 'DEBUG',
        1 => 'INFO',
        2 => 'WARNING',
        3 => 'ERROR',
    ];

    public function __construct($level = self::INFO)
    {
        if (array_key_exists((int) $level, $this->level_value)) {
            $this->level = $level;
        } else {
            $this->level = self::INFO;
        }
    }

    /**
     * Log the message.
     *
     * @param string $message
     * @param int $level
     */
    abstract protected function logMessage($message, $level);

    /**
     * Check the level and log the message if needed.
     *
     * @param string $message
     * @param int $level
     */
    public function log($message, $level = self::DEBUG)
    {
        if ($level >= $this->level) {
            $this->logMessage($message, $level);
        }

        Hook::exec(
            'actionLoggerLogMessage',
            [
                'message' => $message,
                'level' => $level,
                'isLogged' => $level >= $this->level,
            ]
        );
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     */
    public function logDebug($message)
    {
        $this->log($message, self::DEBUG);
    }

    /**
     * Log an info message.
     *
     * @param string $message
     */
    public function logInfo($message)
    {
        $this->log($message, self::INFO);
    }

    /**
     * Log a warning message.
     *
     * @param string $message
     */
    public function logWarning($message)
    {
        $this->log($message, self::WARNING);
    }

    /**
     * Log an error message.
     *
     * @param string $message
     */
    public function logError($message)
    {
        $this->log($message, self::ERROR);
    }
}
