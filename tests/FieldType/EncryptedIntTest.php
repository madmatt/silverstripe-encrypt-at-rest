<?php

namespace Madmatt\EncryptAtRest\Tests\FieldType;

use Madmatt\EncryptAtRest\Tests\Model\EncryptedTestDataObject;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class EncryptedIntTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        EncryptedTestDataObject::class
    ];

    public function testIntStorage()
    {
        $expected = 25;

        /** @var EncryptedTestDataObject $object */
        $object = EncryptedTestDataObject::create();
        $object->IntTest = $expected;

        // Ensure by default without writing that the value is unchanged
        $this->assertEquals($expected, $object->IntTest);

        // After writing, ensure the value is unchanged
        $id = $object->write();

        // Ensure (by direct DB query) that the value is stored in an encrypted form (starts with def)
        $dbValue = DB::prepared_query(
            'select IntTest from EncryptedTestDataObject where ID = ?',
            [$id]
        )->column('IntTest')[0];

        $this->assertNotEquals($expected, $dbValue);
        $this->assertStringStartsWith('def', $dbValue);

        // Ensure that if we re-request the object from the database after clearing the DataObject cache that the value
        // is corectly decrypted during object hydration
        DataObject::flush_and_destroy_cache();
        $object = EncryptedTestDataObject::get()->byID($id);

        // Check that both ways we can access the data is correct
        $this->assertEquals($expected, (int)$object->IntTest);
        $this->assertEquals($expected, (int)$object->dbObject('IntTest')->getValue());
    }
}
