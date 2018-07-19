<?php

namespace Madmatt\EncryptAtRest\Tests\Model;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Class EncryptDataObject
 * @property string EncryptedText
 * @property string UnencryptedText
 */
class EncryptDataObject extends DataObject implements TestOnly
{

    private static $db = array(
        'EncryptedText'   => 'EncryptedVarchar',
        'UnencryptedText' => 'Varchar(255)'
    );
}
