<?php

namespace Madmatt\EncryptAtRest\Tests\Model;

use Madmatt\EncryptAtRest\FieldType\EncryptedDatetime;
use Madmatt\EncryptAtRest\FieldType\EncryptedDecimal;
use Madmatt\EncryptAtRest\FieldType\EncryptedEnum;
use Madmatt\EncryptAtRest\FieldType\EncryptedInt;
use Madmatt\EncryptAtRest\FieldType\EncryptedText;
use Madmatt\EncryptAtRest\FieldType\EncryptedVarchar;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class EncryptedTestDataObject
 * @property string EncryptedText
 * @property string UnencryptedText
 */
class EncryptedTestDataObject extends DataObject implements TestOnly
{
    private static $table_name = 'EncryptedTestDataObject';

    private static $db = array(
        'EncryptedText'   => EncryptedVarchar::class,
        'UnencryptedText' => 'Varchar(255)',

        // All encrypted fields for testing
        'DatetimeTest' => EncryptedDatetime::class,
        'DecimalTest' => EncryptedDecimal::class,
        'EnumTest' => EncryptedEnum::class,
        'IntTest' => EncryptedInt::class,
        'TextTest' => EncryptedText::class,
        'VarcharTest' => EncryptedVarchar::class
    );
}
