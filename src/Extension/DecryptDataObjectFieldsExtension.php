<?php

namespace Madmatt\EncryptAtRest\Extension;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Class DecryptDataObjectFieldsExtension
 *
 * @description DataObjects that contain encrypted fields need the fields to be decrypted, without interfering with the
 * framework itself. The encrypted fields should be accessible in exactly the same way they would be if they weren't
 * encrypted at the database level.
 *
 * @package EncryptAtRest\Extension
 * @property DecryptDataObjectFieldsExtension|DataObject $owner
 */
class DecryptDataObjectFieldsExtension extends DataExtension
{
    /**
     * During hydration of an existing DataObject retrieved from the database, this extension method will be called. We
     * determine if there are any database fields attached to the object being hydrated that need to be decrypted, and
     * if so, we decrypt these and inject them into the object during hydration so that the rest of the application only
     * has to deal with the decrypted values everywhere.
     *
     * @return array
     */
    public function augmentHydrateFields()
    {
        // Look at $this->owner to determine if it has any encrypted database fields
        $schema = DataObject::getSchema();
        $dbFields = $schema->databaseFields($this->owner->ClassName);
        $additionalValues = [];

        // Loop over all $dbFields to find any in the Madmatt\EncryptAtRest\FieldType namespace
        foreach ($dbFields as $fieldName => $className) {
            if (strpos($className, 'Madmatt\EncryptAtRest\FieldType') === 0) {
                // This db field is an encrypted field, we should hydrate the value from the db field
                /** @var DBField $field */
                $field = $this->owner->dbObject($fieldName);

                $additionalValues[$fieldName] = $field->getValue();
            }
        }

        return $additionalValues;
    }
}
