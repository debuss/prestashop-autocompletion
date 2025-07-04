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

namespace PrestaShopBundle\Translation\Loader;

use tools\profiling\Db;
use PrestaShop\PrestaShop\Core\Addon\Theme\Theme;
use PrestaShopException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class SqlTranslationLoader implements LoaderInterface
{
    /**
     * @var Theme the theme
     */
    protected $theme;

    /**
     * @param Theme $theme the theme
     *
     * @return $this
     */
    public function setTheme(Theme $theme)
    {
        $this->theme = $theme;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource, $locale, $domain = 'messages'): MessageCatalogue
    {
        static $localeResults = [];

        if (!array_key_exists($locale, $localeResults)) {
            try {
                $locale = Db::getInstance()->escape($locale, false, true);

                $localeResults[$locale] = Db::getInstance()->getRow(
                    'SELECT `id_lang`
                FROM `' . _DB_PREFIX_ . 'lang`
                WHERE `locale` = "' . $locale . '"'
                );
            } catch (PrestaShopException) {
                // When no DB is created there is nothing to fetch, so we return an empty catalog to avoid breaking process for
                // invalid reasons (like CLI commands before the shop is installed)
                return new MessageCatalogue($locale);
            }
        }

        if (empty($localeResults[$locale])) {
            throw new NotFoundResourceException(sprintf('Language not found in database: %s', $locale));
        }

        // If we get translations for a theme, realistically we need to get translations
        // for all active themes from the database, since different stores can use different themes.
        // If we don't do that, the first store's theme you visit after the cache is cleared will
        // be the only one that has translations.
        $selectTranslationsQuery = '
            SELECT `key`, `translation`, `domain`
            FROM `' . _DB_PREFIX_ . 'translation`
            WHERE `id_lang` = ' . $localeResults[$locale]['id_lang'] . '
            AND theme ' . ($this->theme !== null ? ' IN (SELECT `theme` FROM `' . _DB_PREFIX_ . 'shop` WHERE `active` = 1)' : 'IS NULL');

        $translations = Db::getInstance()->executeS($selectTranslationsQuery) ?: [];

        $catalogue = new MessageCatalogue($locale);
        $this->addTranslationsToCatalogue($translations, $catalogue);

        return $catalogue;
    }

    /**
     * @param array $translations the list of translations
     * @param MessageCatalogueInterface $catalogue the Message Catalogue
     */
    protected function addTranslationsToCatalogue(array $translations, MessageCatalogueInterface $catalogue)
    {
        foreach ($translations as $translation) {
            $catalogue->set($translation['key'], $translation['translation'], $translation['domain']);
        }
    }
}
