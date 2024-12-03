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

class VoucherlyUsers extends ObjectModel
{
    /**
     * @var int
     */
    public int $id_voucherly_users;

    /**
     * @var int
     */
    public int $id_customer;

    /**
     * @var string
     */
    public string $id_voucherly;

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
        'table' => 'voucherly_users',
        'primary' => 'id_voucherly_users',
        'fields' => [
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_voucherly' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
            'ambient' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => false],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    public static function create(int $customerId, string $voucherlyId): VoucherlyUsers
    {
        $voucherlyUser = new VoucherlyUsers();
        $voucherlyUser->id_customer = $customerId;
        $voucherlyUser->id_voucherly = $voucherlyId;
        $voucherlyUser->ambient = self::getVoucherlyAmbient();
        $voucherlyUser->date_add = date('Y-m-d H:i:s');
        $voucherlyUser->save();

        return $voucherlyUser;
    }

    public static function getVoucherlyId(int $customerId): string
    {
        return (string) Db::getInstance()->getValue('
            SELECT id_voucherly FROM `' . _DB_PREFIX_ . 'voucherly_users` vu
            WHERE vu.id_customer = ' . $customerId . '
            AND vu.ambient = "' . self::getVoucherlyAmbient() . '"'
        );
    }    

    private static function getVoucherlyAmbient(): string
    {
        return Configuration::get('VOUCHERLY_SANDBOX', false) ? 't' : 'p';
    }
}
