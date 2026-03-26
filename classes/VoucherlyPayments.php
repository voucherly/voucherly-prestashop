<?php

/**
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
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class VoucherlyPayments extends ObjectModel
{
    /**
     * @var int
     */
    public int $id_voucherly_payments;

    /**
     * @var string
     */
    public string $id_voucherly;

    /**
     * @var float
     */
    public $finalAmount;

    /**
     * @var float
     */
    public $paidAmount;

    /**
     * @var float
     */
    public $paidDigitalAmount;

    /**
     * @var float
     */
    public $paidVoucherAmount;

    /**
     * @var float
     */
    public $paidFringeAmount;

    /**
     * @var float
     */
    public $paidCashAmount;

    /**
     * @var ?string
     */
    public ?string $ambient = null;

    /**
     * @var string
     */
    public string $date_add;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'voucherly_payments',
        'primary' => 'id_voucherly_payments',
        'fields' => [
            'id_voucherly' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
            'final_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true],
            'paid_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true],
            'paid_digital_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true],
            'paid_voucher_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => false],
            'paid_fringe_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => false],
            'paid_cash_amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => false],
            'ambient' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => false],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    public static function create(string $voucherlyId, float $finalAmount, float $paidAmount,
                                  float $paidDigitalAmount, float $paidVoucherAmount,
                                  float $paidFringeAmount, float $paidCashAmount): VoucherlyPayments
    {
        $voucherlyPayment = new VoucherlyPayments();
        $voucherlyPayment->id_voucherly = $voucherlyId;
        $voucherlyPayment->finalAmount = $finalAmount;
        $voucherlyPayment->paidAmount = $paidAmount;
        $voucherlyPayment->paidDigitalAmount = $paidDigitalAmount;
        $voucherlyPayment->paidVoucherAmount = $paidVoucherAmount;
        $voucherlyPayment->paidFringeAmount = $paidFringeAmount;
        $voucherlyPayment->paidCashAmount = $paidCashAmount;
        $voucherlyPayment->ambient = self::getVoucherlyAmbient();
        $voucherlyPayment->date_add = date('Y-m-d H:i:s');
        $voucherlyPayment->save();

        return $voucherlyPayment;
    }

    public static function getByVoucherlyId(string $voucherlyId)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'voucherly_payments` vu
            WHERE vu.id_voucherly = "' . $voucherlyId . '"
            AND vu.ambient = "' . self::getVoucherlyAmbient() . '"' );
    }

    private static function getVoucherlyAmbient(): string
    {
        return Configuration::get('VOUCHERLY_SANDBOX', false) ? 't' : 'p';
    }
}
