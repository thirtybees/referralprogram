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

{extends file="helpers/form/form.tpl"}
{block name="field"}
	{if $input.type == 'discount_value'}
		<div class="col-lg-2">
			<table class="table table-condensed" id="discount_value">
				<thead>
					<tr>
						<th>{l s="Currency" mod='referralprogram'}</th>
						<th>{l s="Voucher amount" mod='referralprogram'}</th>
					</tr>
				</thead>
				{foreach from=$currencies item=currency}
					<tr>
						<td>{$currency.name}</td>
						<td>
							<div class="input-group">
								<input type="text" name="discount_value[{$currency.id_currency}]" id="discount_value[{$currency.id_currency}]" value="{$fields_value[$input.name][{$currency.id_currency}]}" class="">
								<span class="input-group-addon">
								{$currency.sign}
								</span>
							</div>
						</td>
					</tr>
				{/foreach}
			</table>
		</div>
	{/if}
	{$smarty.block.parent}
{/block}
