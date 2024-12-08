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
 * @copyright 2023 Voucherly
 * @license   https://opensource.org/license/gpl-3-0/ GNU General Public License version 3 (GPL-3.0)
 *}

<section id="{$moduleName}-displayAdminOrderMainBottom">
  <div class="card mt-2">
    <div class="card-header">
      <h3 class="card-header-title">
        <img src="{$moduleLogoImageSrc}" alt="{$moduleDisplayName}" width="20" height="20">
        {$moduleDisplayName}
      </h3>
    </div>
    <div class="card-body">
      <p>
        {l s='This order has been paid with %name%.' mod='voucherly' sprintf=['%name%' => $moduleDisplayName]}
        <a href="{$voucherlyLink}" target="_blank">{l s='Click here to see it in the Voucherly dashboard.' mod='voucherly'}</a>
      </p>
    </div>
  </div>
</section>
