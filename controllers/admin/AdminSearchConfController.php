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

use tools\profiling\Db;
use tools\profiling\Tools;

/**
 * @property Alias $object
 */
class AdminSearchConfControllerCore extends AdminController
{
    /** @var bool */
    protected $toolbar_scroll = false;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'alias';
        $this->className = 'Alias';
        $this->lang = false;

        parent::__construct();

        $params = [
            'action' => 'searchCron',
            'ajax' => 1,
            'full' => 1,
            'token' => $this->getTokenForCron(),
        ];
        if (Shop::getContext() == Shop::CONTEXT_SHOP) {
            $params['id_shop'] = (int) Context::getContext()->shop->id;
        }

        // Search options
        $cron_url = Context::getContext()->link->getAdminLink(
            'AdminSearch',
            false,
            [],
            $params
        );

        list($total, $indexed) = Db::getInstance()->getRow('SELECT COUNT(*) as "0", SUM(product_shop.indexed) as "1" FROM ' . _DB_PREFIX_ . 'product p ' . Shop::addSqlAssociation('product', 'p') . ' WHERE product_shop.`visibility` IN ("both", "search") AND product_shop.`active` = 1');

        $this->fields_options = [
            'indexation' => [
                'title' => $this->trans('Indexing', [], 'Admin.Shopparameters.Feature'),
                'icon' => 'icon-cogs',
                'info' => '<p>
						' . $this->trans('The "indexed" products have been analyzed by PrestaShop and will appear in the results of a front office search.', [], 'Admin.Shopparameters.Feature') . '<br />
						' . $this->trans('Indexed products', [], 'Admin.Shopparameters.Feature') . ' <strong>' . (int) $indexed . ' / ' . (int) $total . '</strong>.
					</p>
					<p>
						' . $this->trans('Building the product index may take a few minutes.', [], 'Admin.Shopparameters.Feature') . '
						' . $this->trans('If your server stops before the process ends, you can resume the indexing by clicking "%add_missing_products_label%".', ['%add_missing_products_label%' => $this->trans('Add missing products to the index', [], 'Admin.Shopparameters.Feature')], 'Admin.Shopparameters.Feature') . '
					</p>
					<a href="' . Context::getContext()->link->getAdminLink('AdminSearch', false) . '&action=searchCron&ajax=1&token=' . $this->getTokenForCron() . '&amp;redirect=1' . (Shop::getContext() == Shop::CONTEXT_SHOP ? '&id_shop=' . (int) Context::getContext()->shop->id : '') . '" class="btn-link">
						<i class="icon-external-link-sign"></i>
						' . $this->trans('Add missing products to the index', [], 'Admin.Shopparameters.Feature') . '
					</a><br />
					<a href="' . Context::getContext()->link->getAdminLink('AdminSearch', false) . '&action=searchCron&ajax=1&full=1&amp;token=' . $this->getTokenForCron() . '&amp;redirect=1' . (Shop::getContext() == Shop::CONTEXT_SHOP ? '&id_shop=' . (int) Context::getContext()->shop->id : '') . '" class="btn-link">
						<i class="icon-external-link-sign"></i>
						' . $this->trans('Re-build the entire index', [], 'Admin.Shopparameters.Feature') . '
					</a><br /><br />
					<p>
						' . $this->trans('You can set a cron job that will rebuild your index using the following URL:', [], 'Admin.Shopparameters.Feature') . '<br />
						<a href="' . Tools::safeOutput($cron_url) . '">
							<i class="icon-external-link-sign"></i>
							' . Tools::safeOutput($cron_url) . '
						</a>
					</p><br />',
                'fields' => [
                    'PS_SEARCH_INDEXATION' => [
                        'title' => $this->trans('Indexing', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isBool',
                        'type' => 'bool',
                        'cast' => 'intval',
                        'desc' => $this->trans('Enable the automatic indexing of products. If you enable this feature, the products will be indexed in the search automatically when they are saved. If the feature is disabled, you will have to index products manually by using the links provided in the field set.', [], 'Admin.Shopparameters.Help'),
                    ],
                ],
                'submit' => ['title' => $this->trans('Save', [], 'Admin.Actions')],
            ],
            'search' => [
                'title' => $this->trans('Search', [], 'Admin.Shopparameters.Feature'),
                'icon' => 'icon-search',
                'fields' => [
                    'PS_SEARCH_START' => [
                        'title' => $this->trans('Search within word', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'type' => 'bool',
                        'desc' => $this->trans(
                            'By default, to search for “blouse”, you have to enter “blous”, “blo”, etc (beginning of the word) – but not “lous” (within the word).',
                            [],
                            'Admin.Shopparameters.Help'
                        ) . '<br/>' .
                            $this->trans(
                                'With this option enabled, it also gives the good result if you search for “lous”, “ouse”, or anything contained in the word.',
                                [],
                                'Admin.Shopparameters.Help'
                            ),
                        'hint' => [
                            $this->trans(
                                'Enable search within a whole word, rather than from its beginning only.',
                                [],
                                'Admin.Shopparameters.Help'
                            ),
                            $this->trans(
                                'It checks if the searched term is contained in the indexed word. This may be resource-consuming.',
                                [],
                                'Admin.Shopparameters.Help'
                            ),
                        ],
                    ],
                    'PS_SEARCH_END' => [
                        'title' => $this->trans('Search exact end match', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'type' => 'bool',
                        'desc' => $this->trans(
                            'By default, if you search "book", you will have "book", "bookcase" and "bookend".',
                            [],
                            'Admin.Shopparameters.Help'
                        ) . '<br/>' .
                            $this->trans(
                                'With this option enabled, it only gives one result “book”, as exact end of the indexed word is matching.',
                                [],
                                'Admin.Shopparameters.Help'
                            ),
                        'hint' => [
                            $this->trans(
                                'Enable more precise search with the end of the word.',
                                [],
                                'Admin.Shopparameters.Help'
                            ),
                            $this->trans(
                                'It checks if the searched term is the exact end of the indexed word.',
                                [],
                                'Admin.Shopparameters.Help'
                            ),
                        ],
                    ],
                    'PS_SEARCH_FUZZY' => [
                        'title' => $this->trans('Fuzzy search', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'type' => 'bool',
                        'desc' => $this->trans(
                            'By default, the fuzzy search is enabled. It means spelling errors are allowed, e.g. you can search for "bird" with words like "burd", "bard" or "beerd".',
                            [],
                            'Admin.Shopparameters.Help'
                        ) . '<br/>' .
                            $this->trans(
                                'Disabling this option will require exact spelling for the search to match results.',
                                [],
                                'Admin.Shopparameters.Help'
                            ),
                        'hint' => [
                            $this->trans(
                                'Enable approximate string matching.',
                                [],
                                'Admin.Shopparameters.Help'
                            ),
                        ],
                    ],
                    'PS_SEARCH_FUZZY_MAX_LOOP' => [
                        'title' => $this->trans(
                            'Maximum approximate words allowed by fuzzy search',
                            [],
                            'Admin.Shopparameters.Feature'
                        ),
                        'hint' => $this->trans(
                            'Note that this option is resource-consuming: the more you search, the longer it takes.',
                            [],
                            'Admin.Shopparameters.Help'
                        ),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_FUZZY_MAX_DIFFERENCE' => [
                        'title' => $this->trans(
                            'Maximum acceptable word difference',
                            [],
                            'Admin.Shopparameters.Feature'
                        ),
                        'desc' => $this->trans(
                            'This option defines how much different can the alternative words found by fuzzy search be. Or, how many characters can be different/missing/added. The default value is 5.',
                            [],
                            'Admin.Shopparameters.Help'
                        ),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_MAX_WORD_LENGTH' => [
                        'title' => $this->trans(
                            'Maximum word length (in characters)',
                            [],
                            'Admin.Shopparameters.Feature'
                        ),
                        'hint' => $this->trans(
                            'Only words fewer or equal to this maximum length will be searched.',
                            [],
                            'Admin.Shopparameters.Help'
                        ),
                        'desc' => $this->trans(
                            'This parameter will only be used if the fuzzy search is activated: the lower the value, the more tolerant your search will be.',
                            [],
                            'Admin.Shopparameters.Help'
                        ),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                        'required' => true,
                    ],
                    'PS_SEARCH_MINWORDLEN' => [
                        'title' => $this->trans(
                            'Minimum word length (in characters)',
                            [],
                            'Admin.Shopparameters.Feature'
                        ),
                        'hint' => $this->trans(
                            'Only words this size or larger will be indexed.',
                            [],
                            'Admin.Shopparameters.Help'
                        ),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_BLACKLIST' => [
                        'title' => $this->trans('Blacklisted words', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isGenericName',
                        'hint' => $this->trans(
                            'Please enter the index words separated by a "|".',
                            [],
                            'Admin.Shopparameters.Help'
                        ),
                        'type' => 'textareaLang',
                    ],
                ],
                'submit' => ['title' => $this->trans('Save', [], 'Admin.Actions')],
            ],
            'relevance' => [
                'title' => $this->trans('Weight', [], 'Admin.Shopparameters.Feature'),
                'icon' => 'icon-cogs',
                'info' => $this->trans(
                    'The "weight" represents its importance and relevance for the ranking of the products when completing a new search.',
                    [],
                    'Admin.Shopparameters.Feature'
                ) . '<br />
						' . $this->trans(
                    'A word with a weight of eight will have four times more value than a word with a weight of two.',
                    [],
                    'Admin.Shopparameters.Feature'
                ) . '<br /><br />
						' . $this->trans(
                    'We advise you to set a greater weight for words which appear in the name or reference of a product. This will allow the search results to be as precise and relevant as possible.',
                    [],
                    'Admin.Shopparameters.Feature'
                ) . '<br /><br />
						' . $this->trans(
                    'Setting a weight to 0 will exclude that field from search index. Re-build of the entire index is required when changing to or from 0',
                    [],
                    'Admin.Shopparameters.Feature'
                ),
                'fields' => [
                    'PS_SEARCH_WEIGHT_PNAME' => [
                        'title' => $this->trans('Product name weight', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_WEIGHT_REF' => [
                        'title' => $this->trans('Reference weight', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_WEIGHT_SHORTDESC' => [
                        'title' => $this->trans(
                            'Short description weight',
                            [],
                            'Admin.Shopparameters.Feature'
                        ),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_WEIGHT_DESC' => [
                        'title' => $this->trans('Description weight', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_WEIGHT_CNAME' => [
                        'title' => $this->trans('Category weight', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_WEIGHT_MNAME' => [
                        'title' => $this->trans('Brand weight', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_WEIGHT_TAG' => [
                        'title' => $this->trans('Tags weight', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_WEIGHT_ATTRIBUTE' => [
                        'title' => $this->trans('Attributes weight', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                    'PS_SEARCH_WEIGHT_FEATURE' => [
                        'title' => $this->trans('Features weight', [], 'Admin.Shopparameters.Feature'),
                        'validation' => 'isUnsignedInt',
                        'type' => 'text',
                        'cast' => 'intval',
                    ],
                ],
                'submit' => ['title' => $this->trans('Save', [], 'Admin.Actions')],
            ],
        ];
    }

    public function initPageHeaderToolbar()
    {
        if (empty($this->display) || $this->display == 'list') {
            $this->page_header_toolbar_btn['new_alias'] = [
                'href' => self::$currentIndex . '&addalias&token=' . $this->token,
                'desc' => $this->trans('Add new alias', [], 'Admin.Shopparameters.Feature'),
                'icon' => 'process-icon-new',
            ];
        }
        $this->identifier_name = 'alias';
        parent::initPageHeaderToolbar();
        if ($this->can_import) {
            $this->toolbar_btn['import'] = [
                'href' => $this->context->link->getAdminLink('AdminImport', true) . '&import_type=alias',
                'desc' => $this->trans('Import', [], 'Admin.Actions'),
            ];
        }
    }

    public function initProcess()
    {
        parent::initProcess();
        // This is a composite page, we don't want the "options" display mode
        if ($this->display == 'options') {
            $this->display = '';
        }
    }

    /**
     * Function used to render the options for this controller.
     *
     * @return string|void
     */
    public function renderOptions()
    {
        if ($this->fields_options && is_array($this->fields_options)) {
            $helper = new HelperOptions();
            $this->setHelperDisplay($helper);
            $helper->toolbar_scroll = true;
            $helper->toolbar_btn = ['save' => [
                'href' => '#',
                'desc' => $this->trans('Save', [], 'Admin.Actions'),
            ]];
            $helper->id = $this->id;
            $helper->tpl_vars = $this->tpl_option_vars;
            $options = $helper->generateOptions($this->fields_options);

            return $options;
        }
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Aliases', [], 'Admin.Shopparameters.Feature'),
                'icon' => 'icon-search',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Alias', [], 'Admin.Shopparameters.Feature'),
                    'name' => 'alias',
                    'required' => true,
                    'hint' => [
                        $this->trans('Enter each alias separated by a comma (e.g. \'prestshop,preztashop,prestasohp\').', [], 'Admin.Shopparameters.Help'),
                        $this->trans('Forbidden characters: &lt;&gt;{}', [], 'Admin.Shopparameters.Help'),
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Result', [], 'Admin.Shopparameters.Feature'),
                    'name' => 'search',
                    'required' => true,
                    'hint' => $this->trans('Search this word instead.', [], 'Admin.Shopparameters.Help'),
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Admin.Actions'),
            ],
        ];

        $this->fields_value = ['alias' => $this->object->getAliases()];

        return parent::renderForm();
    }

    public function processSave()
    {
        $search = (string) Tools::getValue('search');
        $string = (string) Tools::getValue('alias');
        $aliases = explode(',', $string);
        if (empty($search) || empty($string)) {
            $this->errors[] = $this->trans('Aliases and results are both required.', [], 'Admin.Shopparameters.Notification');
        }
        if (!Validate::isValidSearch($search)) {
            $this->errors[] = Tools::safeOutput($search) . ' ' . $this->trans('Is not a valid result', [], 'Admin.Shopparameters.Notification');
        }
        foreach ($aliases as $alias) {
            if (!Validate::isValidSearch($alias)) {
                $this->errors[] = Tools::safeOutput($alias) . ' ' . $this->trans('Is not a valid alias', [], 'Admin.Shopparameters.Notification');
            }
        }

        if (!count($this->errors)) {
            // Search existing aliases
            $alias = new Alias();
            $alias->search = trim($search);
            $existingAliases = explode(',', $alias->getAliases());

            // New alias
            $newAliases = array_diff($aliases, $existingAliases);
            foreach ($newAliases as $alias) {
                $obj = new Alias(null, trim($alias), trim($search));
                $obj->save();
            }

            // Removed alias
            $removedAliases = array_diff($existingAliases, $aliases);
            foreach ($removedAliases as $alias) {
                $obj = new Alias(null, trim($alias), trim($search));
                $obj->delete();
            }
        }

        if (empty($this->errors)) {
            if (Tools::getValue('id_alias')) {
                $this->confirmations[] = $this->trans('Update successful', [], 'Admin.Notifications.Success');
            } else {
                $this->confirmations[] = $this->trans('Successful creation', [], 'Admin.Notifications.Success');
            }
        }
    }

    /**
     * Retrieve a part of the cookie key for token check. (needs to be static).
     *
     * @return string Token
     */
    private function getTokenForCron()
    {
        return substr(
            _COOKIE_KEY_,
            AdminSearchController::TOKEN_CHECK_START_POS,
            AdminSearchController::TOKEN_CHECK_LENGTH
        );
    }
}
