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
 * Class ReferralprogramRulesModuleFrontController
 */
class ReferralprogramRulesModuleFrontController extends ModuleFrontController
{
    // @codingStandardsIgnoreStart
    /** @var bool $content_only */
    public $content_only = true;
    /** @var bool $display_header */
    public $display_header = false;
    /** @var bool $display_footer */
    public $display_footer = false;
    /** @var bool $ssl */
    public $ssl = true;
    // @codingStandardsIgnoreEnd

    /**
     * @throws PrestaShopException
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $xmlFile = _PS_MODULE_DIR_.'referralprogram/referralprogram.xml';
        if (file_exists($xmlFile)) {
            if ($xml = @simplexml_load_file($xmlFile)) {
                $this->context->smarty->assign(
                    [
                        'xml'       => $xml,
                        'paragraph' => 'paragraph_'.$this->context->language->id,
                    ]
                );
            }
        }
        $this->setTemplate('rules.tpl');
    }
}
