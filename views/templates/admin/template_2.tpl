{**
 * Copyright (C) 2023  Voucherly
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

<div class="panel">
	<div class="row voucherly-header">
		<img src="{$module_dir|escape:'html':'UTF-8'}views/img/template_2_logo.png" class="col-xs-6 col-md-3 text-center" id="payment-logo" />
		<div class="col-xs-6 col-md-6 text-center text-muted">
			{l s='My Payment Module and PrestaShop have partnered to provide the easiest way for you to accurately calculate and file sales tax.' mod='voucherly'}
		</div>
		<div class="col-xs-12 col-md-3 text-center">
			<a href="#" onclick="javascript:return false;" class="btn btn-primary" id="create-account-btn">{l s='Create an account' mod='voucherly'}</a><br />
			{l s='Already have one?' mod='voucherly'}<a href="#" onclick="javascript:return false;"> {l s='Log in' mod='voucherly'}</a>
		</div>
	</div>

	<hr />
	
	<div class="voucherly-content">
		<div class="row">
			<div class="col-md-5">
				<h5>{l s='Benefits of using my payment module' mod='voucherly'}</h5>
				<ul class="ul-spaced">
					<li>
						<strong>{l s='It is fast and easy' mod='voucherly'}:</strong>
						{l s='It is pre-integrated with PrestaShop, so you can configure it with a few clicks.' mod='voucherly'}
					</li>
					
					<li>
						<strong>{l s='It is global' mod='voucherly'}:</strong>
						{l s='Accept payments in XX currencies from XXX markets around the world.' mod='voucherly'}
					</li>
					
					<li>
						<strong>{l s='It is trusted' mod='voucherly'}:</strong>
						{l s='Industry-leading fraud an buyer protections keep you and your customers safe.' mod='voucherly'}
					</li>
					
					<li>
						<strong>{l s='It is cost-effective' mod='voucherly'}:</strong>
						{l s='There are no setup fees or long-term contracts. You only pay a low transaction fee.' mod='voucherly'}
					</li>
				</ul>
			</div>
			
			<div class="col-md-2">
				<h5>{l s='Pricing' mod='voucherly'}</h5>
				<dl class="list-unstyled">
					<dt>{l s='Payment Standard' mod='voucherly'}</dt>
					<dd>{l s='No monthly fee' mod='voucherly'}</dd>
					<dt>{l s='Payment Express' mod='voucherly'}</dt>
					<dd>{l s='No monthly fee' mod='voucherly'}</dd>
					<dt>{l s='Payment Pro' mod='voucherly'}</dt>
					<dd>{l s='$5 per month' mod='voucherly'}</dd>
				</dl>
				<a href="#" onclick="javascript:return false;">(Detailed pricing here)</a>
			</div>
			
			<div class="col-md-5">
				<h5>{l s='How does it work?' mod='voucherly'}</h5>
				<iframe src="//player.vimeo.com/video/75405291" width="335" height="188" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
			</div>
		</div>

		<hr />
		
		<div class="row">
			<div class="col-md-12">
				<p class="text-muted">{l s='My Payment Module accepts more than 80 localized payment methods around the world' mod='voucherly'}</p>
				
				<div class="row">
					<img src="{$module_dir|escape:'html':'UTF-8'}views/img/template_2_cards.png" class="col-md-3" id="payment-logo" />
					<div class="col-md-9 text-center">
						<h6>{l s='For more information, call 888-888-1234' mod='voucherly'} {l s='or' mod='voucherly'} <a href="mailto:contact@prestashop.com">contact@prestashop.com</a></h6>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="panel">
	<p class="text-muted">
		<i class="icon icon-info-circle"></i> {l s='In order to create a secure account with My Payment Module, please complete the fields in the settings panel below:' mod='voucherly'}
		{l s='By clicking the "Save" button you are creating secure connection details to your store.' mod='voucherly'}
		{l s='My Payment Module signup only begins when you client on "Activate your account" in the registration panel below.' mod='voucherly'}
		{l s='If you already have an account you can create a new shop within your account.' mod='voucherly'}
	</p>
	<p>
		<a href="#" onclick="javascript:return false;"><i class="icon icon-file"></i> Link to the documentation</a>
	</p>
</div>