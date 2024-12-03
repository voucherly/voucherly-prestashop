{**
 * Copyright (C) 2024 Voucherly
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @author    Voucherly <info@voucherly.it>
 * @copyright 2024 Voucherly
 * @license   https://opensource.org/license/gpl-3-0/ GNU General Public License version 3 (GPL-3.0)
 *}

<div class="row">
	<div class="col-xs-12">
		<p class="payment_module" id="voucherly_payment_button">
			<a href="{$link->getModuleLink('voucherly', 'payment', array(), true)|escape:'htmlall':'UTF-8'}" title="{l s='Pay with Voucherly' mod='voucherly'}">
				{* <img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/payment_logo.png" alt="{l s='Pay with Voucherly' mod='voucherly'}" width="20" height="20" /> *}
				{l s='Pay with Voucherly' mod='voucherly'}
			</a>
		</p>
	</div>
</div>
