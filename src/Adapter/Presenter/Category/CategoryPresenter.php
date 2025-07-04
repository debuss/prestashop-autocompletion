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

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Presenter\Category;

use Category;
use tools\profiling\Hook;
use Language;
use Link;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;

class CategoryPresenter
{
    /**
     * @var ImageRetriever
     */
    protected $imageRetriever;

    /**
     * @var Link
     */
    protected $link;

    public function __construct(Link $link)
    {
        $this->link = $link;
        $this->imageRetriever = new ImageRetriever($link);
    }

    /**
     * @param array|Category $category Category object or an array
     * @param Language $language
     *
     * @return CategoryLazyArray
     */
    public function present(array|Category $category, Language $language): CategoryLazyArray
    {
        // Convert to array if a Category object was passed
        if (is_object($category)) {
            $category = (array) $category;
        }

        // Normalize IDs
        if (empty($category['id_category'])) {
            $category['id_category'] = $category['id'];
        }
        if (empty($category['id'])) {
            $category['id'] = $category['id_category'];
        }

        $categoryLazyArray = new CategoryLazyArray(
            $category,
            $language,
            $this->imageRetriever,
            $this->link
        );

        Hook::exec('actionPresentCategory',
            ['presentedCategory' => &$categoryLazyArray]
        );

        return $categoryLazyArray;
    }
}
