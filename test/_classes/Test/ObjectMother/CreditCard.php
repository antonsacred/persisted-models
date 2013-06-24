<?php
namespace Test\ObjectMother;

use Test\CreditCard\Model;
use Test\CreditCard\Properties;

class CreditCard
{
    public static function datatransTesting($id = null)
    {
        return new Model(new Properties(array(
            'system' => 'VISA',
            'pan' => '9500000000000001',
            'validMonth' => '12',
            'validYear' => '2015',
            'ccv' => '234',
            'cardholderName' => 'Maxim Gnatenko'
        )));
    }

}
