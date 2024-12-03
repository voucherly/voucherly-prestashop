<?php

/**
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
 */
require_once dirname(__FILE__) . '/lib/Api.php';
require_once dirname(__FILE__) . '/lib/NotSuccessException.php';
require_once dirname(__FILE__) . '/lib/Request.php';
require_once dirname(__FILE__) . '/lib/PaymentHelper.php';

require_once dirname(__FILE__) . '/lib/Payment/Payment.php';
require_once dirname(__FILE__) . '/lib/Payment/CreatePayment/CreatePaymentRequest.php';
require_once dirname(__FILE__) . '/lib/Payment/CreatePayment/CreatePaymentRequestDiscount.php';
require_once dirname(__FILE__) . '/lib/Payment/CreatePayment/CreatePaymentRequestLine.php';

require_once dirname(__FILE__) . '/lib/PaymentGateway/PaymentGateway.php';
