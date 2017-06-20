{*
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
*}

<!-- MODULE ReferralProgram -->
<p id="referralprogram">
	<img src="{$module_template_dir}referralprogram.gif" alt="{l s='Referral program' mod='referralprogram'}" class="icon" />
	{l s='You have earned a voucher worth %s thanks to your sponsor!' sprintf=$discount_display mod='referralprogram'}
	{l s='Enter voucher name %s to receive the reduction on this order.' sprintf=$discount->name mod='referralprogram'}
	<a href="{$link->getModuleLink('referralprogram', 'program', [], true)|escape:'html'}" title="{l s='Referral program' mod='referralprogram'}" rel="nofollow">{l s='View your referral program.' mod='referralprogram'}</a>
</p>
<br />
<!-- END : MODULE ReferralProgram -->
