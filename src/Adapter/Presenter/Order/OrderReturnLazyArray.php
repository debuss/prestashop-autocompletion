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

namespace PrestaShop\PrestaShop\Adapter\Presenter\Order;

use Link;
use PrestaShop\PrestaShop\Adapter\Presenter\AbstractLazyArray;
use PrestaShop\PrestaShop\Adapter\Presenter\LazyArrayAttribute;
use PrestaShopException;
use ReflectionException;
use tools\profiling\Tools;

class OrderReturnLazyArray extends AbstractLazyArray
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @var Link
     */
    private $link;

    /** @var array */
    private $orderReturn;

    /**
     * OrderReturnLazyArray constructor.
     *
     * @param string $prefix
     * @param Link $link
     * @param array $orderReturn
     *
     * @throws ReflectionException
     */
    public function __construct($prefix, Link $link, array $orderReturn)
    {
        $this->prefix = $prefix;
        $this->link = $link;
        $this->orderReturn = $orderReturn;
        parent::__construct();
        $this->appendArray($orderReturn);
    }

    /**
     * @return mixed
     */
    #[LazyArrayAttribute(arrayAccess: true)]
    public function getId()
    {
        return $this->orderReturn['id_order_return'];
    }

    /**
     * @return string
     */
    #[LazyArrayAttribute(arrayAccess: true)]
    public function getDetailsUrl()
    {
        return $this->link->getPageLink(
            'order-detail',
            null,
            null,
            'id_order=' . (int) $this->orderReturn['id_order']
        );
    }

    /**
     * @return string
     */
    #[LazyArrayAttribute(arrayAccess: true)]
    public function getReturnUrl()
    {
        return $this->link->getPageLink(
            'order-return',
            null,
            null,
            'id_order_return=' . (int) $this->orderReturn['id_order_return']
        );
    }

    /**
     * @return string
     */
    #[LazyArrayAttribute(arrayAccess: true)]
    public function getReturnNumber()
    {
        return $this->prefix . sprintf('%06d', $this->orderReturn['id_order_return']);
    }

    /**
     * @return string
     *
     * @throws PrestaShopException
     */
    #[LazyArrayAttribute(arrayAccess: true)]
    public function getReturnDate()
    {
        return Tools::displayDate($this->orderReturn['date_add'], false);
    }

    /**
     * @return string
     */
    #[LazyArrayAttribute(arrayAccess: true)]
    public function getPrintUrl()
    {
        return ($this->orderReturn['state'] == 2)
            ? $this->link->getPageLink(
                'pdf-order-return',
                null,
                null,
                'id_order_return=' . (int) $this->orderReturn['id_order_return']
            )
            : '';
    }
}
