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

namespace PrestaShop\PrestaShop\Adapter\Category\QueryHandler;

use Category;
use tools\profiling\Db;
use ImageManager;
use PDO;
use PrestaShop\PrestaShop\Adapter\SEO\RedirectTargetProvider;
use PrestaShop\PrestaShop\Core\CommandBus\Attributes\AsQueryHandler;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CannotEditRootCategoryException;
use PrestaShop\PrestaShop\Core\Domain\Category\Exception\CategoryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Category\Query\GetCategoryForEditing;
use PrestaShop\PrestaShop\Core\Domain\Category\QueryHandler\GetCategoryForEditingHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Category\QueryResult\EditableCategory;
use PrestaShop\PrestaShop\Core\Domain\Category\ValueObject\CategoryId;
use PrestaShop\PrestaShop\Core\Image\Parser\ImageTagSourceParserInterface;
use Shop;

/**
 * Class GetCategoryForEditingHandler.
 */
#[AsQueryHandler]
final class GetCategoryForEditingHandler implements GetCategoryForEditingHandlerInterface
{
    public function __construct(
        private readonly ImageTagSourceParserInterface $imageTagSourceParser,
        private readonly RedirectTargetProvider $targetProvider,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @throws CategoryNotFoundException
     * @throws CannotEditRootCategoryException
     */
    public function handle(GetCategoryForEditing $query)
    {
        $category = new Category($query->getCategoryId()->getValue());

        if (!$category->id || (!$category->isAssociatedToShop() && Shop::getContext() == Shop::CONTEXT_SHOP)) {
            throw new CategoryNotFoundException($query->getCategoryId(), sprintf('Category with id "%s" was not found', $query->getCategoryId()->getValue()));
        }

        if ($category->isRootCategory()) {
            throw new CannotEditRootCategoryException();
        }

        /**
         * Select recursivly the subcategories in one SQL request
         */
        $subcategories = Db::getInstance()->query(
            'SELECT id_category ' .
            'FROM ( ' .
            '  SELECT * FROM `' . _DB_PREFIX_ . 'category`' .
            '  ORDER BY id_parent, id_category' .
            ') category_sorted, ' .
            '(SELECT @pv := ' . (int) $category->id . ') initialisation ' .
            'WHERE FIND_IN_SET(id_parent, @pv) ' .
            'AND LENGTH(@pv := CONCAT(@pv, \',\', id_category))'
        );

        $categoryRedirectTarget = $this->targetProvider->getRedirectTarget(
            $category->redirect_type,
            $category->id_type_redirected,
        );

        $editableCategory = new EditableCategory(
            $query->getCategoryId(),
            $category->name,
            (bool) $category->active,
            $category->description,
            (int) $category->id_parent,
            $category->meta_title,
            $category->meta_description,
            $category->link_rewrite,
            $category->redirect_type,
            $categoryRedirectTarget,
            $category->getGroups(),
            $category->getAssociatedShops(),
            (bool) $category->is_root_category,
            $this->getCoverImage($query->getCategoryId()),
            $this->getThumbnailImage($query->getCategoryId()),
            $subcategories->fetchAll(PDO::FETCH_COLUMN),
            $category->additional_description
        );

        return $editableCategory;
    }

    /**
     * @param CategoryId $categoryId
     *
     * @return array|null cover image data or null if category does not have cover
     */
    private function getCoverImage(CategoryId $categoryId)
    {
        $imageType = 'jpg';
        $image = _PS_CAT_IMG_DIR_ . $categoryId->getValue() . '.' . $imageType;

        $imageTag = ImageManager::thumbnail(
            $image,
            'category_' . $categoryId->getValue() . '.' . $imageType,
            350,
            $imageType,
            true,
            true
        );

        $imageSize = file_exists($image) ? filesize($image) / 1000 : '';

        if (empty($imageTag) || empty($imageSize)) {
            return null;
        }

        return [
            'size' => sprintf('%skB', $imageSize),
            'path' => $this->imageTagSourceParser->parse($imageTag),
        ];
    }

    /**
     * @param CategoryId $categoryId
     *
     * @return array|null
     */
    private function getThumbnailImage(CategoryId $categoryId)
    {
        $imageType = 'jpg';
        $image = _PS_CAT_IMG_DIR_ . $categoryId->getValue() . '_thumb.' . $imageType;

        $imageTag = ImageManager::thumbnail(
            $image,
            'category_' . $categoryId->getValue() . '_thumb.' . $imageType,
            350,
            $imageType,
            true,
            true
        );

        $imageSize = file_exists($image) ? filesize($image) / 1000 : '';

        if (empty($imageTag) || empty($imageSize)) {
            return null;
        }

        return [
            'size' => sprintf('%skB', $imageSize),
            'path' => $this->imageTagSourceParser->parse($imageTag),
        ];
    }
}
