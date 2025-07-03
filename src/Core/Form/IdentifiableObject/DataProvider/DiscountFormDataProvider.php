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

namespace PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataProvider;

use PrestaShop\PrestaShop\Adapter\Attribute\Repository\AttributeRepository;
use PrestaShop\PrestaShop\Adapter\Product\Repository\ProductRepository;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Context\LanguageContext;
use PrestaShop\PrestaShop\Core\Context\ShopContext;
use PrestaShop\PrestaShop\Core\Domain\Discount\DiscountSettings;
use PrestaShop\PrestaShop\Core\Domain\Discount\Query\GetDiscountForEditing;
use PrestaShop\PrestaShop\Core\Domain\Discount\QueryResult\DiscountForEditing;
use PrestaShop\PrestaShop\Core\Domain\Language\ValueObject\LanguageId;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\ValueObject\CombinationId;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Provider\ProductImageProviderInterface;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductId;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopAssociationNotFound;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopException;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopId;
use PrestaShop\PrestaShop\Core\Product\Combination\NameBuilder\CombinationNameBuilder;

class DiscountFormDataProvider implements FormDataProviderInterface
{
    public function __construct(
        private readonly CommandBusInterface $queryBus,
        private readonly ProductRepository $productRepository,
        private readonly CombinationNameBuilder $combinationNameBuilder,
        private readonly ProductImageProviderInterface $productImageProvider,
        private readonly LanguageContext $languageContext,
        private readonly AttributeRepository $attributeRepository,
        private readonly ShopContext $shopContext,
    ) {
    }

    public function getDefaultData()
    {
        return [];
    }

    /**
     * @throws ShopException
     * @throws ProductNotFoundException
     * @throws ProductConstraintException
     * @throws CombinationConstraintException
     */
    public function getData($id)
    {
        /** @var DiscountForEditing $discountForEditing */
        $discountForEditing = $this->queryBus->handle(new GetDiscountForEditing($id));
        $isAmountDiscount = $discountForEditing->getAmountDiscount() !== null;
        $details = $this->getGiftDetails($discountForEditing);

        $selectedCondition = 'none';
        $selectedCartCondition = 'none';
        if ($discountForEditing->getMinimumProductQuantity()) {
            $selectedCondition = 'cart_conditions';
            $selectedCartCondition = 'minimum_product_quantity';
        } elseif ($discountForEditing->getMinimumAmount()) {
            $selectedCondition = 'cart_conditions';
            $selectedCartCondition = 'minimum_amount';
        }

        return [
            'id' => $id,
            'information' => [
                'discount_type' => $discountForEditing->getType()->getValue(),
                'names' => $discountForEditing->getLocalizedNames(),
            ],
            'value' => [
                'reduction' => [
                    'type' => $isAmountDiscount ? DiscountSettings::AMOUNT : DiscountSettings::PERCENT,
                    'value' => $isAmountDiscount
                        ? (float) (string) $discountForEditing->getAmountDiscount()
                        : (float) (string) $discountForEditing->getPercentDiscount(),
                    'currency' => $discountForEditing->getCurrencyId(),
                    'include_tax' => $discountForEditing->isTaxIncluded(),
                ],
            ],
            'free_gift' => [
                [
                    'product_id' => $discountForEditing->getGiftProductId(),
                    'combination_id' => $discountForEditing->getGiftCombinationId(),
                    'name' => $details['name'],
                    'image' => $details['imageUrl'],
                ],
            ],
            'conditions' => [
                'children_selector' => $selectedCondition,
                'cart_conditions' => [
                    'children_selector' => $selectedCartCondition,
                    'minimum_product_quantity' => $discountForEditing->getMinimumProductQuantity(),
                    'minimum_amount' => [
                        'value' => $discountForEditing->getMinimumAmount() ? (float) (string) $discountForEditing->getMinimumAmount() : null,
                        'currency' => $discountForEditing->getMinimumAmountCurrencyId(),
                        'include_tax' => $discountForEditing->getMinimumAmountTaxIncluded(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws ShopAssociationNotFound
     * @throws ShopException
     * @throws ProductConstraintException
     * @throws ProductNotFoundException
     * @throws CombinationConstraintException
     */
    private function getGiftDetails(DiscountForEditing $discountForEditing): array
    {
        $name = '';
        $imageUrl = '';
        if (!empty($discountForEditing->getGiftProductId())) {
            $product = $this->productRepository->getProductByDefaultShop(new ProductId($discountForEditing->getGiftProductId()));
            $name = $product->name[$this->languageContext->getId()];

            if (!empty($discountForEditing->getGiftCombinationId())) {
                $attributesInformations = $this->attributeRepository->getAttributesInfoByCombinationIds(
                    [new CombinationId($discountForEditing->getGiftCombinationId())],
                    new LanguageId($this->languageContext->getId())
                );

                $name = $this->combinationNameBuilder->buildFullName(
                    $name,
                    $attributesInformations[$discountForEditing->getGiftCombinationId()]
                );
                $imageUrl = $this->productImageProvider->getCombinationCoverUrl(
                    new CombinationId($discountForEditing->getGiftCombinationId()),
                    new ShopId($this->shopContext->getId())
                );
            } else {
                $imageUrl = $this->productImageProvider->getProductCoverUrl(
                    new ProductId($discountForEditing->getGiftProductId()),
                    new ShopId($this->shopContext->getId())
                );
            }
        }

        return [
            'name' => $name,
            'imageUrl' => $imageUrl,
        ];
    }
}
