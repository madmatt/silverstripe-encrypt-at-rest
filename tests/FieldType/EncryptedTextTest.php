<?php

namespace Madmatt\EncryptAtRest\Tests\FieldType;

use Madmatt\EncryptAtRest\Tests\Model\EncryptedTestDataObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class EncryptedTextTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        EncryptedTestDataObject::class
    ];

    public function testTextStorage()
    {
        $expected = 'This is a text string';

        /** @var EncryptedTestDataObject $object */
        $object = EncryptedTestDataObject::create();
        $object->TextTest = $expected;

        // Ensure by default without writing that the value is unchanged
        $this->assertEquals($expected, $object->TextTest);

        // After writing, ensure the value is unchanged
        $id = $object->write();

        // Ensure (by direct DB query) that the value is stored in an encrypted form (starts with def)
        $dbValue = DB::prepared_query(
            'select TextTest from EncryptedTestDataObject where ID = ?',
            [$id]
        )->column('TextTest')[0];

        $this->assertNotEquals($expected, $dbValue);
        $this->assertStringStartsWith('def', $dbValue);

        // Ensure that if we re-request the object from the database after clearing the DataObject cache that the value
        // is corectly decrypted during object hydration
        DataObject::flush_and_destroy_cache();
        $object = EncryptedTestDataObject::get()->byID($id);

        // Check that both ways we can access the data is correct
        $this->assertEquals($expected, $object->TextTest);
        $this->assertEquals($expected, $object->dbObject('TextTest')->getValue());
    }
}
