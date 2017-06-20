<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/ReferralProgramModule.php';

/**
 * Class ReferralProgram
 */
class ReferralProgram extends Module
{
    const DISCOUNT_PERCENT = 1;
    const DISCOUNT_AMOUNT = 2;

    public $localConfiguration = [];
    public $moduleXmlFile;
    public $moduleHtml;

    /**
     * ReferralProgram constructor.
     */
    public function __construct()
    {
        $this->name = 'referralprogram';
        $this->tab = 'advertising_marketing';
        $this->version = '2.0.0';
        $this->author = 'PrestaShop';

        $this->controllers = ['program'];

        $this->bootstrap = true;
        parent::__construct();

        $this->confirmUninstall = $this->l('All sponsors and friends will be deleted. Are you sure you want to uninstall this module?');
        $this->displayName = $this->l('Customer referral program');
        $this->description = $this->l('Integrate a referral program system into your shop.');
        if (Configuration::get('REFERRAL_DISCOUNT_TYPE') == 1 && !Configuration::get('REFERRAL_PERCENTAGE')) {
            $this->warning = $this->l('Please specify an amount for referral program vouchers.');
        }

        if ($this->id) {
            $this->localConfiguration = Configuration::getMultiple(['REFERRAL_NB_FRIENDS', 'REFERRAL_ORDER_QUANTITY', 'REFERRAL_DISCOUNT_TYPE', 'REFERRAL_DISCOUNT_VALUE']);
            $this->localConfiguration['REFERRAL_DISCOUNT_DESCRIPTION'] = Configuration::getInt('REFERRAL_DISCOUNT_DESCRIPTION');
            $this->moduleXmlFile = __DIR__.'/referralprogram.xml';
        }
    }

    /**
     * Format discount
     *
     * @param float $value
     * @param int   $type
     * @param null  $currency
     *
     * @return string
     */
    public static function formatDiscount($value, $type, $currency = null)
    {
        if ((float) $value && (int) $type) {
            if ($type === 1) {
                return $value.chr(37);
            } // ASCII #37 --> % (percent)
            elseif ($type == 2) {
                return Tools::displayPrice($value, $currency);
            }
        }

        return ''; // return a string because it's a display method
    }

    /**
     * Reset the module
     *
     * @return bool
     */
    public function reset()
    {
        if (!$this->uninstall(false)) {
            return false;
        }
        if (!$this->install(false)) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall this module
     *
     * @param bool $deleteParams
     *
     * @return bool
     */
    public function uninstall($deleteParams = true)
    {
        $result = true;
        foreach (Currency::getCurrencies() as $currency) {
            $result = $result && Configuration::deleteByName('REFERRAL_DISCOUNT_VALUE_'.(int) ($currency['id_currency']));
        }

        if ($deleteParams && !$this->uninstallDB()) {
            return false;
        }

        if (!parent::uninstall()
            || !$this->removeMail()
            || !$result
            || !Configuration::deleteByName('REFERRAL_PERCENTAGE')
            || !Configuration::deleteByName('REFERRAL_ORDER_QUANTITY')
            || !Configuration::deleteByName('REFERRAL_DISCOUNT_TYPE')
            || !Configuration::deleteByName('REFERRAL_NB_FRIENDS')
            || !Configuration::deleteByName('REFERRAL_DISCOUNT_DESCRIPTION')
            || !Configuration::deleteByName('REFERRAL_DISCOUNT_CUMULATIVE')
            || !Configuration::deleteByName('REFERRAL_TAX')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Drop database table
     *
     * @return bool
     */
    public function uninstallDB()
    {
        return Db::getInstance()->execute('DROP TABLE `'._DB_PREFIX_.'referralprogram`;');
    }

    /**
     * Remove mail templates
     *
     * @return bool
     */
    public function removeMail()
    {
        $langs = Language::getLanguages(false);
        foreach ($langs as $lang) {
            foreach (['referralprogram-congratulations', 'referralprogram-invitation', 'referralprogram-voucher'] as $name) {
                foreach (['txt', 'html'] as $ext) {
                    $file = _PS_MAIL_DIR_.$lang['iso_code'].'/'.$name.'.'.$ext;
                    if (file_exists($file) && !@unlink($file)) {
                        $this->_errors[] = $this->l('Cannot delete this file:').' '.$file;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Install this module
     *
     * @param bool $deleteParams
     *
     * @return bool
     */
    public function install($deleteParams = true)
    {
        $defaultTranslations = ['en' => 'Referral reward', 'fr' => 'Récompense parrainage'];
        $desc = [(int) Configuration::get('PS_LANG_DEFAULT') => $this->l('Referral reward')];
        foreach (Language::getLanguages() as $language) {
            if (isset($defaultTranslations[$language['iso_code']])) {
                $desc[(int) $language['id_lang']] = $defaultTranslations[$language['iso_code']];
            }
        }

        if (!parent::install()
            || !Configuration::updateValue('REFERRAL_DISCOUNT_DESCRIPTION', $desc)
            || !Configuration::updateValue('REFERRAL_ORDER_QUANTITY', 1)
            || !Configuration::updateValue('REFERRAL_DISCOUNT_TYPE', 2)
            || !Configuration::updateValue('REFERRAL_NB_FRIENDS', 5)
            || !Configuration::updateValue('REFERRAL_DISCOUNT_CUMULATIVE', 1)
            || !$this->registerHook('shoppingCart')
            || !$this->registerHook('orderConfirmation')
            || !$this->registerHook('updateOrderStatus')
            || !$this->registerHook('adminCustomers')
            || !$this->registerHook('createAccount')
            || !$this->registerHook('createAccountForm')
            || !$this->registerHook('customerAccount')
        ) {
            return false;
        }

        if ($deleteParams && !$this->installDB()) {
            return false;
        }

        /* Define a default value for fixed amount vouchers, for each currency */
        foreach (Currency::getCurrencies() as $currency) {
            Configuration::updateValue('REFERRAL_DISCOUNT_VALUE_'.(int) ($currency['id_currency']), 5);
        }

        /* Define a default value for the amount tax */
        Configuration::updateValue('REFERRAL_TAX', 1);

        /* Define a default value for the percentage vouchers */
        Configuration::updateValue('REFERRAL_PERCENTAGE', 5);

        /* This hook is optional */
        $this->registerHook('displayMyAccountBlock');

        return true;
    }

    /**
     * Install database table
     *
     * @return bool
     */
    public function installDB()
    {
        return Db::getInstance()->execute(
            '
                CREATE TABLE `'._DB_PREFIX_.'referralprogram` (
                  `id_referralprogram`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `id_sponsor`           INT UNSIGNED NOT NULL,
                  `email`                VARCHAR(255) NOT NULL,
                  `lastname`             VARCHAR(128) NOT NULL,
                  `firstname`            VARCHAR(128) NOT NULL,
                  `id_customer`          INT UNSIGNED NULL,
                  `id_cart_rule`         INT UNSIGNED NULL,
                  `id_cart_rule_sponsor` INT UNSIGNED NULL,
                  `date_add`             DATETIME     NOT NULL,
                  `date_upd`             DATETIME     NOT NULL,
                  PRIMARY KEY (`id_referralprogram`),
                  UNIQUE KEY `index_unique_referralprogram_email` (`email`)
                ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;'
        );
    }

    public function getContent()
    {
        $this->moduleHtml = '';

        if (Tools::isSubmit('submitReferralProgram')) {
            $this->postValidation();
            if (!sizeof($this->_errors)) {
                $this->postProcess();
            } else {
                foreach ($this->_errors as $err) {
                    $this->moduleHtml .= $err;
                }
            }
        } elseif (Tools::isSubmit('submitText')) {
            $this->writeXml();
        }

        $this->moduleHtml .= $this->renderForm();

        return $this->moduleHtml;
    }

    /**
     * Write content
     *
     * @param mixed $key
     * @param mixed $field
     * @param mixed $forbidden
     * @param mixed $section
     *
     * @return int|string
     */
    public function putContent($key, $field, $forbidden, $section)
    {
        foreach ($forbidden as $line) {
            if ($key === $line) {
                return 0;
            }
        }
        if (!preg_match('/^'.$section.'_/i', $key)) {
            return 0;
        }
        $key = preg_replace('/^'.$section.'_/i', '', $key);
        $field = Tools::htmlentitiesDecodeUTF8(htmlspecialchars($field));
        if (!$field) {
            return 0;
        }

        return ("\n\t\t".'<'.$key.'><![CDATA['.$field.']]></'.$key.'>');
    }

    /**
     * Render form
     *
     * @return string
     */
    public function renderForm()
    {
        $fieldsForm1 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'  => 'text',
                        'label' => $this->l('Minimum number of orders a customer must place to become a sponsor'),
                        'name'  => 'order_quantity',
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Number of friends in the referral program invitation form (customer account, referral program section):'),
                        'name'  => 'nb_friends',
                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Combine vouchers'),
                        'name'   => 'discount_cumulative',
                        'desc'   => $this->l('If enabled, a customer can use several vouchers for a same order.'),
                        'hint'   => $this->l('A customer can have several active vouchers. Do you allow these vouchers to be combined on a single purchase?'),
                        'values' => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type'   => 'radio',
                        'label'  => $this->l('Voucher type :'),
                        'name'   => 'discount_type',
                        'class'  => 't',
                        'values' => [
                            [
                                'id'    => 'discount_type1',
                                'value' => 1,
                                'label' => $this->l('Voucher offering a percentage'),
                            ],
                            [
                                'id'    => 'discount_type2',
                                'value' => 2,
                                'label' => $this->l('Voucher offering a fixed amount (by currency)'),
                            ],
                        ],
                    ],
                    [
                        'type'   => 'text',
                        'label'  => $this->l('Percentage'),
                        'name'   => 'discount_value_percentage',
                        'suffix' => '%',
                    ],
                    [
                        'type'  => 'discount_value',
                        'label' => $this->l('Voucher amount'),
                        'name'  => 'discount_value',
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Voucher tax'),
                        'name'    => 'discount_tax',
                        'options' => [
                            'query' => [
                                ['id' => 0, 'name' => $this->l('Tax excluded')],
                                ['id' => 1, 'name' => $this->l('Tax included')],
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        ],
                    ],
                    [
                        'type'  => 'text',
                        'label' => $this->l('Voucher description'),
                        'name'  => 'discount_description',
                        'lang'  => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'submitReferralProgram',
                ],
            ],
        ];

        $fieldsForm2 = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Conditions of the referral program'),
                    'icon'  => 'icon-cogs',
                ],
                'input'  => [
                    [
                        'type'         => 'textarea',
                        'autoload_rte' => true,
                        'label'        => $this->l('Text'),
                        'name'         => 'body_paragraph',
                        'lang'         => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'submitText',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitModule';
        $helper->module = $this;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'currencies'   => Currency::getCurrencies(),
            'fields_value' => $this->getConfigFieldsValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];

        $helper->override_folder = '/';

        return $this->renderJs().$helper->generateForm([$fieldsForm1, $fieldsForm2]);
    }

    /**
     * Get configuration field values
     *
     * @return array
     */
    public function getConfigFieldsValues()
    {
        $fieldsValues = [
            'order_quantity'            => Tools::getValue('order_quantity', Configuration::get('REFERRAL_ORDER_QUANTITY')),
            'discount_type'             => Tools::getValue('discount_type', Configuration::get('REFERRAL_DISCOUNT_TYPE')),
            'nb_friends'                => Tools::getValue('nb_friends', Configuration::get('REFERRAL_NB_FRIENDS')),
            'discount_value_percentage' => Tools::getValue('discount_value_percentage', Configuration::get('REFERRAL_PERCENTAGE')),
            'discount_tax'              => Tools::getValue('discount_tax', Configuration::get('REFERRAL_TAX')),
            'discount_cumulative'       => Tools::getValue('discount_cumulative', Configuration::get('REFERRAL_DISCOUNT_CUMULATIVE')),
        ];

        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $fieldsValues['discount_description'][$lang['id_lang']] = Tools::getValue('discount_description_'.(int) $lang['id_lang'], Configuration::get('REFERRAL_DISCOUNT_DESCRIPTION', (int) $lang['id_lang']));
            $fieldsValues['body_paragraph'][$lang['id_lang']] = '';
        }

        $currencies = Currency::getCurrencies();
        foreach ($currencies as $currency) {
            $fieldsValues['discount_value'][$currency['id_currency']] = Tools::getValue('discount_value['.(int) $currency['id_currency'].']', Configuration::get('REFERRAL_DISCOUNT_VALUE_'.(int) $currency['id_currency']));
        }

        // xml loading
        if (file_exists($this->moduleXmlFile)) {
            if ($xml = @simplexml_load_file($this->moduleXmlFile)) {
                foreach ($languages as $lang) {
                    $key = 'paragraph_'.$lang['id_lang'];
                    $fieldsValues['body_paragraph'][$lang['id_lang']] = Tools::getValue('body_paragraph_'.(int) $lang['id_lang'], (string) $xml->body->$key);
                }
            }
        }

        return $fieldsValues;
    }

    /**
     * Render javascript
     *
     * @return string
     */
    public function renderJs()
    {
        return $this->display(__FILE__, 'views/templates/hook/js.tpl');
    }

    /**
     * Hook call when cart created and updated
     * Display the discount name if the sponsor friend have one
     *
     * @param array $params
     *
     * @return bool|string
     */
    public function hookShoppingCart($params)
    {
        include_once(dirname(__FILE__).'/ReferralProgramModule.php');

        if (!isset($params['cart']->id_customer)) {
            return false;
        }
        if (!($idReferralprogram = ReferralProgramModule::isSponsorised((int) ($params['cart']->id_customer), true))) {
            return false;
        }
        $referralprogram = new ReferralProgramModule($idReferralprogram);
        if (!Validate::isLoadedObject($referralprogram)) {
            return false;
        }
        $cartRule = new CartRule($referralprogram->id_cart_rule);
        if (!Validate::isLoadedObject($cartRule)) {
            return false;
        }

        if ($cartRule->checkValidity($this->context, false, false) === true) {
            $this->smarty->assign(['discount_display' => ReferralProgram::displayDiscount($cartRule->reduction_percent ? $cartRule->reduction_percent : $cartRule->reduction_amount, $cartRule->reduction_percent ? 1 : 2, new Currency($params['cookie']->id_currency)), 'discount' => $cartRule]);

            return $this->display(__FILE__, 'shopping-cart.tpl');
        }

        return false;
    }

    /**
     * Display discount
     *
     * @param mixed $discountValue
     * @param int   $discountType
     * @param bool  $currency
     *
     * @return string
     */
    public static function displayDiscount($discountValue, $discountType, $currency = false)
    {
        if ((float) $discountValue && (int) $discountType) {
            if ($discountType === ReferralProgram::DISCOUNT_PERCENT) {
                return $discountValue.chr(37);
            } // ASCII #37 --> % (percent)
            elseif ($discountType === ReferralProgram::DISCOUNT_AMOUNT) {
                return Tools::displayPrice($discountValue, $currency);
            }
        }

        return ''; // return a string because it's a display method
    }

    public function hookDisplayMyAccountBlock()
    {
        return $this->hookCustomerAccount();
    }

    /**
     * Hook display on customer account page
     * Display an additional link on my-account and block my-account
     *
     * @return string
     */
    public function hookCustomerAccount()
    {
        return $this->display(__FILE__, 'my-account.tpl');
    }

    /**
     * Hook display on form create account
     * Add an additional input on bottom for fill the sponsor's e-mail address
     *
     * @return string
     */
    public function hookCreateAccountForm()
    {
        include_once(dirname(__FILE__).'/ReferralProgramModule.php');

        if (Configuration::get('PS_CIPHER_ALGORITHM')) {
            $cipherTool = new Rijndael(_RIJNDAEL_KEY_, _RIJNDAEL_IV_);
        } else {
            $cipherTool = new Blowfish(_COOKIE_KEY_, _COOKIE_IV_);
        }
        $explodeResult = explode('|', $cipherTool->decrypt(Tools::getValue('sponsor')));
        if ($explodeResult
            && count($explodeResult) > 1
            && list($idReferralprogram) = $explodeResult
            && isset($idReferralprogram)
            && (int) $idReferralprogram
            && !empty($email)
            && Validate::isEmail($email) && $idReferralprogram == ReferralProgramModule::isEmailExists($email)
        ) {
            $referralprogram = new ReferralProgramModule($idReferralprogram);
            if (Validate::isLoadedObject($referralprogram)) {
                /* hack for display referralprogram information in form */
                $_POST['customer_firstname'] = $referralprogram->firstname;
                $_POST['firstname'] = $referralprogram->firstname;
                $_POST['customer_lastname'] = $referralprogram->lastname;
                $_POST['lastname'] = $referralprogram->lastname;
                $_POST['email'] = $referralprogram->email;
                $_POST['email_create'] = $referralprogram->email;
                $sponsor = new Customer((int) $referralprogram->id_sponsor);
                $_POST['referralprogram'] = $sponsor->email;
            }
        }

        return $this->display(__FILE__, 'authentication.tpl');
    }

    /**
     * Hook called on creation customer account
     * Create a discount for the customer if sponsorised
     *
     * @param array $params
     *
     * @return bool
     */
    public function hookCreateAccount($params)
    {
        $newCustomer = $params['newCustomer'];
        if (!Validate::isLoadedObject($newCustomer)) {
            return false;
        }
        $postVars = $params['_POST'];
        if (empty($postVars) || !isset($postVars['referralprogram']) || empty($postVars['referralprogram'])) {
            return false;
        }
        $sponsorEmail = $postVars['referralprogram'];
        if (!Validate::isEmail($sponsorEmail) || $sponsorEmail == $newCustomer->email) {
            return false;
        }

        $sponsor = new Customer();
        if ($sponsor = $sponsor->getByEmail($sponsorEmail, null, $this->context)) {
            include_once(dirname(__FILE__).'/ReferralProgramModule.php');

            /* If the customer was not invited by the sponsor, we create the invitation dynamically */
            if (!$idReferralprogram = ReferralProgramModule::isEmailExists($newCustomer->email, true, false)) {
                $referralprogram = new ReferralProgramModule();
                $referralprogram->id_sponsor = (int) $sponsor->id;
                $referralprogram->firstname = $newCustomer->firstname;
                $referralprogram->lastname = $newCustomer->lastname;
                $referralprogram->email = $newCustomer->email;
                if (!$referralprogram->validateFields(false)) {
                    return false;
                } else {
                    $referralprogram->save();
                }
            } else {
                $referralprogram = new ReferralProgramModule((int) $idReferralprogram);
            }

            if ($referralprogram->id_sponsor == $sponsor->id) {
                $referralprogram->id_customer = (int) $newCustomer->id;
                $referralprogram->save();
                if ($referralprogram->registerDiscountForSponsored((int) $params['cookie']->id_currency)) {
                    $cartRule = new CartRule((int) $referralprogram->id_cart_rule);
                    if (Validate::isLoadedObject($cartRule)) {
                        $data = [
                            '{firstname}'      => $newCustomer->firstname,
                            '{lastname}'       => $newCustomer->lastname,
                            '{voucher_num}'    => $cartRule->code,
                            '{voucher_amount}' => (Configuration::get('REFERRAL_DISCOUNT_TYPE') == 2 ? Tools::displayPrice((float) Configuration::get('REFERRAL_DISCOUNT_VALUE_'.(int) $this->context->currency->id), (int) Configuration::get('PS_CURRENCY_DEFAULT')) : (float) Configuration::get('REFERRAL_PERCENTAGE').'%'),
                        ];

                        $cookie = $this->context->cookie;

                        Mail::Send(
                            (int) $cookie->id_lang,
                            'referralprogram-voucher',
                            Mail::l('Congratulations!', (int) $cookie->id_lang),
                            $data,
                            $newCustomer->email,
                            $newCustomer->firstname.' '.$newCustomer->lastname,
                            strval(Configuration::get('PS_SHOP_EMAIL')),
                            strval(Configuration::get('PS_SHOP_NAME')),
                            null,
                            null,
                            dirname(__FILE__).'/mails/'
                        );
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Hook display in tab AdminCustomers on BO
     * Data table with all sponsors informations for a customer
     *
     * @param array $params
     *
     * @return string
     */
    public function hookAdminCustomers($params)
    {
        $customer = new Customer((int) $params['id_customer']);
        $sponsor = null;

        if (!Validate::isLoadedObject($customer)) {
            die ($this->l('Incorrect Customer object.'));
        }

        $friends = ReferralProgramModule::getSponsorFriend((int) $customer->id);
        if ($idReferralprogram = ReferralProgramModule::isSponsorised((int) $customer->id, true)) {
            $referralprogram = new ReferralProgramModule((int) $idReferralprogram);
            $sponsor = new Customer((int) $referralprogram->id_sponsor);
        }

        foreach ($friends as $key => &$friend) {
            $friend['orders_count'] = sizeof(Order::getCustomerOrders($friend['id_customer']));
            $friend['date_add'] = Tools::displayDate($friend['date_add'], null, true);
            $friend['sponsored_friend_count'] = sizeof(ReferralProgramModule::getSponsorFriend($friend['id_customer']));
        }

        $this->smarty->assign(
            [
                'friends'         => $friends,
                'sponsor'         => $sponsor,
                'customer'        => $customer,
                'admin_image_dir' => _PS_ADMIN_IMG_,
                'token'           => Tools::getAdminToken('AdminCustomers'.(int) (Tab::getIdFromClassName('AdminCustomers')).(int) $this->context->employee->id),
            ]
        );

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
            return $this->display(__FILE__, 'hook_customers_16.tpl');
        } else {
            return $this->display(__FILE__, 'hook_customers.tpl');
        }
    }

    /**
     * Hook called when a order is confimed
     * display a message to customer about sponsor discount
     *
     * @param array $params
     *
     * @return bool|string
     */
    public function hookOrderConfirmation($params)
    {
        if ($params['objOrder'] && !Validate::isLoadedObject($params['objOrder'])) {
            return die($this->l('Incorrect Order object.'));
        }

        include_once(dirname(__FILE__).'/ReferralProgramModule.php');

        $customer = new Customer((int) $params['objOrder']->id_customer);
        $stats = $customer->getStats();
        $nbOrdersCustomer = (int) $stats['nb_orders'] + 1; // hack to count current order
        $referralprogram = new ReferralProgramModule(ReferralProgramModule::isSponsorised((int) $customer->id, true));
        if (!Validate::isLoadedObject($referralprogram)) {
            return false;
        }
        $sponsor = new Customer((int) $referralprogram->id_sponsor);
        if ((int) $nbOrdersCustomer == (int) $this->localConfiguration['REFERRAL_ORDER_QUANTITY']) {
            $cartRule = new CartRule((int) $referralprogram->id_cart_rule_sponsor);
            if (!Validate::isLoadedObject($cartRule)) {
                return false;
            }
            $this->smarty->assign(['discount' => ReferralProgram::displayDiscount($cartRule->reduction_percent ? $cartRule->reduction_percent : $cartRule->reduction_amount, $cartRule->reduction_percent ? 1 : 2, new Currency((int) $params['objOrder']->id_currency)), 'sponsor_firstname' => $sponsor->firstname, 'sponsor_lastname' => $sponsor->lastname]);

            return $this->display(__FILE__, 'order-confirmation.tpl');
        }

        return false;
    }

    /**
     * Hook called when order status changed
     * register a discount for sponsor and send him an e-mail
     *
     * @param array $params
     *
     * @return bool
     */
    public function hookUpdateOrderStatus($params)
    {
        if (!Validate::isLoadedObject($params['newOrderStatus'])) {
            die ($this->l('Missing parameters'));
        }
        $orderState = $params['newOrderStatus'];
        $order = new Order((int) ($params['id_order']));
        if ($order && !Validate::isLoadedObject($order)) {
            die($this->l('Incorrect Order object.'));
        }

        include_once(dirname(__FILE__).'/ReferralProgramModule.php');

        $customer = new Customer((int) $order->id_customer);
        $stats = $customer->getStats();
        $nbOrdersCustomer = (int) $stats['nb_orders'] + 1; // hack to count current order
        $referralprogram = new ReferralProgramModule(ReferralProgramModule::isSponsorised((int) ($customer->id), true));
        if (!Validate::isLoadedObject($referralprogram)) {
            return false;
        }
        $sponsor = new Customer((int) $referralprogram->id_sponsor);
        if ((int) $orderState->logable
            && $nbOrdersCustomer >= (int) $this->localConfiguration['REFERRAL_ORDER_QUANTITY']
            && $referralprogram->registerDiscountForSponsor((int) $order->id_currency)
        ) {
            $cartRule = new CartRule((int) $referralprogram->id_cart_rule_sponsor);
            $currency = new Currency((int) $order->id_currency);
            $discountDisplay = ReferralProgram::displayDiscount((float) $cartRule->reduction_percent ? (float) $cartRule->reduction_percent : (int) $cartRule->reduction_amount, (float) $cartRule->reduction_percent ? 1 : 2, $currency);
            $data = ['{sponsored_firstname}' => $customer->firstname, '{sponsored_lastname}' => $customer->lastname, '{discount_display}' => $discountDisplay, '{discount_name}' => $cartRule->code];
            Mail::Send((int) $order->id_lang, 'referralprogram-congratulations', Mail::l('Congratulations!', (int) $order->id_lang), $data, $sponsor->email, $sponsor->firstname.' '.$sponsor->lastname, strval(Configuration::get('PS_SHOP_EMAIL')), strval(Configuration::get('PS_SHOP_NAME')), null, null, dirname(__FILE__).'/mails/');

            return true;
        }

        return false;
    }

    /**
     * Validate post vars
     */
    protected function postValidation()
    {
        $this->_errors = [];
        if (!(int) (Tools::getValue('order_quantity')) || Tools::getValue('order_quantity') < 0) {
            $this->_errors[] = $this->displayError($this->l('Order quantity is required/invalid.'));
        }
        if (!is_array(Tools::getValue('discount_value'))) {
            $this->_errors[] = $this->displayError($this->l('Discount value is invalid.'));
        }
        foreach (Tools::getValue('discount_value') as $idCurrency => $discountValue) {
            if ($discountValue == '') {
                $this->_errors[] = $this->displayError(sprintf($this->l('Discount value for the currency #%d is empty.'), $idCurrency));
            } elseif (!Validate::isUnsignedFloat($discountValue)) {
                $this->_errors[] = $this->displayError(sprintf($this->l('Discount value for the currency #%d is invalid.'), $idCurrency));
            }
        }
        if (!(int) (Tools::getValue('discount_type')) || Tools::getValue('discount_type') < 1 || Tools::getValue('discount_type') > 2) {
            $this->_errors[] = $this->displayError($this->l('Discount type is required/invalid.'));
        }
        if (!(int) (Tools::getValue('nb_friends')) || Tools::getValue('nb_friends') < 0) {
            $this->_errors[] = $this->displayError($this->l('Number of friends is required/invalid.'));
        }
        if ((int) (Tools::getValue('discount_type')) === 1) {
            if (!(int) (Tools::getValue('discount_value_percentage'))
                || (int) (Tools::getValue('discount_value_percentage')) < 0
                || (int) (Tools::getValue('discount_value_percentage')) > 100
            ) {
                $this->_errors[] = $this->displayError($this->l('Discount percentage is required/invalid.'));
            }
        }
    }

    /**
     * Post process
     */
    protected function postProcess()
    {
        Configuration::updateValue('REFERRAL_ORDER_QUANTITY', (int) (Tools::getValue('order_quantity')));
        foreach (Tools::getValue('discount_value') as $idCurrency => $discountValue) {
            Configuration::updateValue('REFERRAL_DISCOUNT_VALUE_'.(int) $idCurrency, (float) $discountValue);
        }
        Configuration::updateValue('REFERRAL_TAX', (int) (Tools::getValue('discount_tax')));
        Configuration::updateValue('REFERRAL_DISCOUNT_TYPE', (int) (Tools::getValue('discount_type')));
        Configuration::updateValue('REFERRAL_NB_FRIENDS', (int) (Tools::getValue('nb_friends')));
        Configuration::updateValue('REFERRAL_PERCENTAGE', (int) (Tools::getValue('discount_value_percentage')));
        Configuration::updateValue('REFERRAL_DISCOUNT_CUMULATIVE', (int) (Tools::getValue('discount_cumulative')));
        foreach (Language::getLanguages(false) as $lang) {
            Configuration::updateValue('REFERRAL_DISCOUNT_DESCRIPTION', [$lang['id_lang'] => Tools::getValue('discount_description_'.(int) $lang['id_lang'])]);
        }

        $this->moduleHtml .= $this->displayConfirmation($this->l('Configuration updated.'));
    }

    /**
     * Write xml
     */
    protected function writeXml()
    {
        $forbiddenKey = ['submitUpdate']; // Forbidden key

        // Generate new XML data
        $newXml = '<'.'?xml version=\'1.0\' encoding=\'utf-8\' ?>'."\n";
        $newXml .= '<referralprogram>'."\n";
        $newXml .= "\t".'<body>';
        // Making body data
        foreach (Language::getLanguages(false) as $lang) {
            if ($line = $this->putContent('body_paragraph_'.(int) $lang['id_lang'], Tools::getValue('body_paragraph_'.(int) $lang['id_lang']), $forbiddenKey, 'body')) {
                $newXml .= $line;
            }
        }

        $newXml .= "\n\t".'</body>'."\n";
        $newXml .= '</referralprogram>'."\n";

        /* write it into the editorial xml file */
        if ($fd = @fopen($this->moduleXmlFile, 'w')) {
            if (!@fwrite($fd, $newXml)) {
                $this->moduleHtml .= $this->displayError($this->l('Unable to write to the xml file.'));
            }
            if (!@fclose($fd)) {
                $this->moduleHtml .= $this->displayError($this->l('Cannot close the xml file.'));
            }
        } else {
            $this->moduleHtml .= $this->displayError($this->l('Unable to update the xml file. Please check the xml file\'s writing permissions.'));
        }
    }
}
