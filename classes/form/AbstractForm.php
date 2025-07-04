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
use PrestaShop\PrestaShop\Core\Foundation\Templating\RenderableProxy;
use Symfony\Contracts\Translation\TranslatorInterface;
use tools\profiling\Tools;

abstract class AbstractFormCore implements FormInterface
{
    /**
     * @var Smarty
     */
    private $smarty;
    /**
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * @var ValidateConstraintTranslator
     */
    protected $constraintTranslator;

    /**
     * @var FormFormatterInterface
     */
    protected $formatter;

    protected $action;
    protected $template;

    /**
     * @var array
     */
    protected $formFields = [];
    /**
     * @var array[]
     */
    protected $errors = ['' => []];

    public function __construct(
        Smarty $smarty,
        TranslatorInterface $translator,
        FormFormatterInterface $formatter
    ) {
        $this->smarty = $smarty;
        $this->translator = $translator;
        $this->formatter = $formatter;
        $this->constraintTranslator = new ValidateConstraintTranslator(
            $this->translator
        );
    }

    public function getFormatter()
    {
        return $this->formatter;
    }

    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getErrors()
    {
        foreach ($this->formFields as $field) {
            $this->errors[$field->getName()] = $field->getErrors();
        }

        return $this->errors;
    }

    public function hasErrors()
    {
        foreach ($this->getErrors() as $errors) {
            if (!empty($errors)) {
                return true;
            }
        }

        return false;
    }

    abstract public function getTemplateVariables();

    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function render(array $extraVariables = [])
    {
        $scope = $this->smarty->createData(
            $this->smarty
        );

        $context = Context::getContext();
        $theme = $context->shop->theme->getName();

        $scope->assign($extraVariables);
        $scope->assign($this->getTemplateVariables());

        $tpl = $this->smarty->createTemplate(
            $this->getTemplate(),
            $scope,
            $theme
        );

        return $tpl->fetch();
    }

    public function getProxy()
    {
        return new RenderableProxy($this);
    }

    public function validate()
    {
        foreach ($this->formFields as $field) {
            if ($field->isRequired()) {
                if (!$field->getValue()) {
                    $field->addError(
                        $this->constraintTranslator->translate('required')
                    );

                    continue;
                } elseif (!$this->checkFieldMaxLength($field)) {
                    $field->addError(
                        $this->translator->trans(
                            'The %1$s field is too long (%2$d chars max).',
                            [$field->getLabel(), $field->getMaxLength()],
                            'Shop.Notifications.Error'
                        )
                    );
                } elseif (!$this->checkFieldMinLength($field)) {
                    $field->addError(
                        $this->translator->trans(
                            'The %1$s field is too short (%2$d chars min).',
                            [$field->getLabel(), $field->getMinLength()],
                            'Shop.Notifications.Error'
                        )
                    );
                }
            } else {
                if (!$field->getValue()) {
                    continue;
                } elseif (!$this->checkFieldMaxLength($field)) {
                    $field->addError(
                        $this->translator->trans(
                            'The %1$s field is too long (%2$d chars max).',
                            [$field->getLabel(), $field->getMaxLength()],
                            'Shop.Notifications.Error'
                        )
                    );
                } elseif (!$this->checkFieldMinLength($field)) {
                    $field->addError(
                        $this->translator->trans(
                            'The %1$s field is too short (%2$d chars min).',
                            [$field->getLabel(), $field->getMinLength()],
                            'Shop.Notifications.Error'
                        )
                    );
                }
            }

            foreach ($field->getConstraints() as $constraint) {
                if (!Validate::$constraint($field->getValue())) {
                    $field->addError(
                        $this->constraintTranslator->translate($constraint)
                    );
                }
            }
        }

        return !$this->hasErrors();
    }

    public function fillWith(array $params = [])
    {
        $newFields = $this->formatter->getFormat();

        foreach ($newFields as $field) {
            if (array_key_exists($field->getName(), $this->formFields)) {
                // keep current value if set
                $field->setValue($this->formFields[$field->getName()]->getValue());
            }

            if (array_key_exists($field->getName(), $params)) {
                // overwrite it if necessary
                $field->setValue($params[$field->getName()]);
            } elseif ($field->getType() === 'checkbox') {
                // checkboxes that are not submitted
                // are interpreted as booleans switched off
                if (empty($field->getValue())) {
                    $field->setValue(false);
                }
            }
        }

        $this->formFields = $newFields;

        return $this;
    }

    public function getField($field_name)
    {
        if (array_key_exists($field_name, $this->formFields)) {
            return $this->formFields[$field_name];
        }

        return null;
    }

    public function getValue($field_name)
    {
        if ($field = $this->getField($field_name)) {
            return $field->getValue();
        }

        return null;
    }

    public function setValue($field_name, $value)
    {
        $this->getField($field_name)->setValue($value);

        return $this;
    }

    /**
     * Validate field length
     *
     * @deprecated Since 9.0 and will be removed in 10.0 - Please use `checkFieldMaxLength`
     *
     * @param FormField $field the field to check
     *
     * @return bool
     */
    protected function checkFieldLength($field)
    {
        return $this->checkFieldMaxLength($field);
    }

    /**
     * Validate field length
     *
     * @param FormField $field the field to check
     *
     * @return bool
     */
    protected function checkFieldMaxLength(FormField $field): bool
    {
        $error = $field->getMaxLength() != null && Tools::strlen($field->getValue()) > (int) $field->getMaxLength();

        return !$error;
    }

    /**
     * Validate field length
     *
     * @param FormField $field the field to check
     *
     * @return bool
     */
    protected function checkFieldMinLength(FormField $field): bool
    {
        $error = $field->getMinLength() != null && Tools::strlen($field->getValue()) < (int) $field->getMinLength();

        return !$error;
    }
}
