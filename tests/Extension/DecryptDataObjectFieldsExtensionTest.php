<?php

namespace Madmatt\EncryptAtRest\Tests;

use Madmatt\EncryptAtRest\Tests\Model\EncryptedTestDataObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * Test encryption on dataobjects.
 * Class EncryptedDataObjectTest
 */
class DecryptDataObjectFieldsExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        EncryptedTestDataObject::class
    ];

    public function testVarcharNotEncryptedBeforeWrite()
    {
        $text = 'This is a random test';
        /** @var EncryptedTestDataObject $object */
        $object = EncryptedTestDataObject::create();

        $object->EncryptedText = $text;
        $this->assertEquals($text, $object->getField('EncryptedText'));

        $object->UnencryptedText = $text;
        $this->assertEquals($text, $object->getField('UnencryptedText'));
    }


    public function testEncryptedVarcharValuesAfterWrite()
    {
        $text = 'This is a random test';
        $controlText = $text . " unencrypted";
        /** @var EncryptedTestDataObject $object */
        $object = EncryptedTestDataObject::create();
        $object->EncryptedText = $text;
        $object->UnencryptedText = $controlText;
        // Write
        $id = $object->write();
        $object->flushCache();

        /** @var EncryptedTestDataObject $retrieved */
        $retrieved = EncryptedTestDataObject::get()->byID($id);

        // Check classes match
        $this->assertEquals(EncryptedTestDataObject::class, $retrieved->ClassName);

        // Check that plain string matches the value supplied by the property (after hydration)
        $this->assertEquals($text, $retrieved->EncryptedText);

        // Check that plain string matches the value supplied by the ORM
        $this->assertEquals($text, $retrieved->getField('EncryptedText'));

        // Check that raw value is the decrypted value, same as the DB field
        $this->assertEquals($retrieved->EncryptedText, $retrieved->dbObject('EncryptedText')->getValue());

        // Check that unencrypted sample is still plain
        $this->assertEquals($controlText, $retrieved->getField('UnencryptedText'));
    }


    public function testEncryptedVarcharAreDecryptedOnGet()
    {
        $text = 'This is a random test';
        $controlText = $text . " unencrypted";
        /** @var EncryptedTestDataObject $object */
        $object = EncryptedTestDataObject::create();
        $object->EncryptedText = $text;
        $object->UnencryptedText = $controlText;
        // Write
        $ID = $object->write();
        $object->flushCache();

        /** @var EncryptedTestDataObject $retrieved */
        $retrieved = EncryptedTestDataObject::get()->filter(array('ID' => $ID))->first();

        // Check that the supplied value is
        $this->assertEquals($text, $retrieved->dbObject('EncryptedText')->getValue());
        $this->assertEquals($controlText, $retrieved->UnencryptedText);
    }
}
