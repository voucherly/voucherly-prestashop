<?php
class Customer extends CustomerCore
{
    public $voucherly_metadata;

    public static $definition = array(
        'table' => 'customer',
        'primary' => 'id_customer',
        'fields' => array(
            'voucherly_metadata' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
        ),
    );
}