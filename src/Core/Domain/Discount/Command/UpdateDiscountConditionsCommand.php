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

namespace PrestaShop\PrestaShop\Core\Domain\Discount\Command;

use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Currency\ValueObject\CurrencyId;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Discount\ProductRuleGroup;
use PrestaShop\PrestaShop\Core\Domain\Discount\ValueObject\DiscountId;
use PrestaShop\PrestaShop\Core\Domain\ValueObject\Money;

class UpdateDiscountConditionsCommand
{
    private DiscountId $discountId;

    private ?int $minimumProductsQuantity = null;

    private ?array $productConditions = null;

    private ?Money $minimumAmount = null;

    private ?bool $minimumAmountShippingIncluded = null;

    public function __construct(int $discountId)
    {
        $this->discountId = new DiscountId($discountId);
    }

    public function getDiscountId(): DiscountId
    {
        return $this->discountId;
    }

    public function getMinimumProductsQuantity(): ?int
    {
        return $this->minimumProductsQuantity;
    }

    public function setMinimumProductsQuantity(int $minimumProductsQuantity): self
    {
        if ($minimumProductsQuantity < 0) {
            throw new DiscountConstraintException('Minimum products quantity must be greater than 0', DiscountConstraintException::INVALID_MINIMUM_PRODUCT_QUANTITY);
        }

        $this->minimumProductsQuantity = $minimumProductsQuantity;

        return $this;
    }

    public function getMinimumAmount(): ?Money
    {
        return $this->minimumAmount;
    }

    public function getMinimumAmountShippingIncluded(): ?bool
    {
        return $this->minimumAmountShippingIncluded;
    }

    public function setMinimumAmount(DecimalNumber $amountDiscount, int $currencyId, bool $taxIncluded, bool $minimumAmountShippingIncluded): self
    {
        if ($amountDiscount->isLowerThanZero()) {
            throw new DiscountConstraintException(sprintf('Money amount cannot be lower than zero, %s given', $amountDiscount), DiscountConstraintException::INVALID_DISCOUNT_VALUE_CANNOT_BE_NEGATIVE);
        }

        $this->minimumAmount = new Money($amountDiscount, new CurrencyId($currencyId), $taxIncluded);
        $this->minimumAmountShippingIncluded = $minimumAmountShippingIncluded;

        return $this;
    }

    public function getProductConditions(): ?array
    {
        return $this->productConditions;
    }

    /**
     * So far the form UX only allows ONE condition group, the legacy allowed for more conditions.
     * We prefer to leave this possibility open in case some business logic are changed. If we are
     * sure of the new behaviour after the testing phase this cas be refactored.
     *
     * Note: it also allows for more possibilities on the API side, however in this case maybe the
     * form should be able to display multiple groups even if it doesn't allow creating them.
     *
     * @param ProductRuleGroup[] $productConditions
     *
     * @return self
     *
     * @throws DiscountConstraintException
     */
    public function setProductConditions(array $productConditions): self
    {
        foreach ($productConditions as $productCondition) {
            if (!$productCondition instanceof ProductRuleGroup) {
                throw new DiscountConstraintException(sprintf('Product conditions must be an array of %s', ProductRuleGroup::class), DiscountConstraintException::INVALID_PRODUCTS_CONDITIONS);
            }
        }

        $this->productConditions = $productConditions;

        return $this;
    }
}
