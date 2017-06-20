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

/**
 * Class ReferralprogramEmailModuleFrontController
 */
class ReferralprogramEmailModuleFrontController extends ModuleFrontController
{
    // @codingStandardsIgnoreStart
    /** @var bool $content_only */
    public $content_only = true;
    /** @var bool $display_header */
    public $display_header = false;
    /** @var bool $display_footer */
    public $display_footer = false;
    // @codingStandardsIgnoreEnd

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $shopName = htmlentities(Configuration::get('PS_SHOP_NAME'), null, 'utf-8');
        $shopUrl = Tools::getHttpHost(true, true);
        $customer = Context::getContext()->customer;

        if (!preg_match("#.*\.html$#Ui", Tools::getValue('mail'))
            || !preg_match("#.*\.html$#Ui", Tools::getValue('mail'))) {
            Tools::redirect('index.php');

            die();
        }

        $filePath = __DIR__.'/../../mails/'.strval(preg_replace('#\.{2,}#', '.', Tools::getValue('mail')));

        if (!file_exists($filePath)) {
            Tools::redirect('index.php');
        }

        $file = file_get_contents($filePath);

        $file = str_replace('{shop_name}', $shopName, $file);
        $file = str_replace('{shop_url}', $shopUrl.__PS_BASE_URI__, $file);
        $file = str_replace('{shop_logo}', $shopUrl._PS_IMG_.'logo.jpg', $file);
        $file = str_replace('{firstname}', $customer->firstname, $file);
        $file = str_replace('{lastname}', $customer->lastname, $file);
        $file = str_replace('{email}', $customer->email, $file);
        $file = str_replace('{firstname_friend}', 'XXXXX', $file);
        $file = str_replace('{lastname_friend}', 'xxxxxx', $file);
        $file = str_replace('{link}', 'authentication.php?create_account=1', $file);
        $discountType = (int) (Configuration::get('REFERRAL_DISCOUNT_TYPE'));
        if ($discountType === ReferralProgram::DISCOUNT_PERCENT) {
            $file = str_replace('{discount}', ReferralProgram::formatDiscount((float) (Configuration::get('REFERRAL_PERCENTAGE')), $discountType, new Currency($this->context->currency->id)), $file);
        } else {
            $file = str_replace('{discount}', ReferralProgram::formatDiscount((float) (Configuration::get('REFERRAL_DISCOUNT_VALUE_'.$this->context->currency->id)), $discountType, new Currency($this->context->currency->id)), $file);
        }

        $this->context->smarty->assign(['content' => $file]);

        $this->setTemplate('email.tpl');
    }
}
