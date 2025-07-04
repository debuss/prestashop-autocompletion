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

namespace PrestaShopBundle\Form\Admin\Type;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This form type is basically a choice types, but it offers a more enriched UX
 * instead of relying on radio buttons each choice is displayed with a div block
 * in which you can specify more details that the option name:
 *   - add help message for more details about the choice
 *   - add icon on each choice
 *
 * Note: so far only tested with radio buttons (expanded: true, multiple: false), other
 * configurations will likely need appropriate improvements at least in the PrestaShop
 * UI kit form theme.
 */
class EnrichedChoiceType extends ChoiceType
{
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['flex_direction'] = $options['flex_direction'];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'expanded' => true,
            'multiple' => false,
            'form_theme' => '@PrestaShop/Admin/TwigTemplateForm/enriched_choice.html.twig',
            'flex_direction' => 'column',
        ]);
        $resolver->setAllowedValues('flex_direction', ['column', 'row']);
    }

    public function getBlockPrefix(): string
    {
        return 'enriched_choice';
    }
}
