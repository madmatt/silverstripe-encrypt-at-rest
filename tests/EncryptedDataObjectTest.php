<?php

namespace Madmatt\EncryptAtRest\Tests;

use Madmatt\EncryptAtRest\Tests\Model\EncryptDataObject;
use SilverStripe\Dev\SapphireTest;

/**
 * Test encryption on dataobjects.
 * Class EncryptedDataObjectTest
 */
class EncryptedDataObjectTest extends SapphireTest
{

    protected static $extra_dataobjects = [
        EncryptDataObject::class
    ];

    public function testVarcharNotEncryptedBeforeWrite()
    {
        $text = 'This is a random test';
        /** @var EncryptDataObject $object */
        $object = EncryptDataObject::create();
        $object->UnencryptedText = $text;
        $this->assertEquals($text, $object->getField('UnencryptedText'));
    }


    public function testEncryptedVarcharValuesAfterWrite()
    {
        $text = 'This is a random test';
        $controlText = $text . " unencrypted";
        /** @var EncryptDataObject $object */
        $object = EncryptDataObject::create();
        $object->EncryptedText = $text;
        $object->UnencryptedText = $controlText;
        // Write
        $ID = $object->write();
        $object->flushCache();

        /** @var EncryptDataObject $retrieved */
        $retrieved = EncryptDataObject::get()->filter(array('ID' => $ID))->first();

        // Check classes match
        $this->assertEquals(EncryptDataObject::class, $retrieved->ClassName);

        // Check that plain string does not match value supplied by property
        $this->assertNotEquals($text, $retrieved->EncryptedText);

        // Check that plain string does not match value supplied by ORM
        $this->assertNotEquals($text, $retrieved->getField('EncryptedText'));

        // Check that raw value is not the decrypted value
        $this->assertNotEquals($retrieved->EncryptedText, $retrieved->dbObject('EncryptedText')->getValue());

        // Check that unencryped sample is still plain
        $this->assertEquals($controlText, $retrieved->getField('UnencryptedText'));
    }


    public function testEncryptedVarcharAreDecryptedOnGet()
    {
        $text = 'This is a random test';
        $controlText = $text . " unencrypted";
        /** @var EncryptDataObject $object */
        $object = EncryptDataObject::create();
        $object->EncryptedText = $text;
        $object->UnencryptedText = $controlText;
        // Write
        $ID = $object->write();
        $object->flushCache();

        /** @var EncryptDataObject $retrieved */
        $retrieved = EncryptDataObject::get()->filter(array('ID' => $ID))->first();

        // Check that the supplied value is
        $this->assertEquals($text, $retrieved->dbObject('EncryptedText')->getValue());
        $this->assertEquals($controlText, $retrieved->UnencryptedText);
    }
}
