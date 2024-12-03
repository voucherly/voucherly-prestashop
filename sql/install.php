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
if (!defined('_PS_VERSION_')) {
    exit;
}
$sql = [];

$columnExists = Db::getInstance()->executeS('
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = "' . pSQL(_DB_PREFIX_) . 'customer" 
    AND TABLE_SCHEMA = DATABASE() 
    AND COLUMN_NAME = "voucherly_metadata"
');

if (!$columnExists) {
    // Esegui l'ALTER TABLE solo se la colonna non esiste
    $sql[] = 'ALTER TABLE `' . pSQL(_DB_PREFIX_) . 'customer` ADD voucherly_metadata LONGTEXT DEFAULT NULL;';
}

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
