<?php

/**
 * Class EncryptDataObjectFieldsExtension
 * @description DataObjects that contain encrypted fields need the fields to be encrypted and decrypted, without interfering
 * with the framework itself. It's fields should be accessible the way they would be as if they're not encrypted.
 *
 * @package EncryptAtRest\Extensions
 *
 * @property EncryptDataObjectFieldsExtension|DataObject owner
 */
class EncryptDataObjectFieldsExtension extends DataExtension
{

    protected static $db_mapping = array();

    /**
     * @config YML
     * EncryptedDataObjectFieldsExtension:
     *   EncryptedDataObjects:
     *     - MyDataObject
     */
    private static $encrypted_data_objects;

    public function __call($name, $arguments)
    {
        return $this->getDecryptedProperty(self::$db_mapping[$name]);
    }


    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $dbFields = Config::inst()->get($this->owner->ClassName, 'db', Config::UNINHERITED);
        $changedFields = (is_array($this->owner->getChangedFields())) ? $this->owner->getChangedFields() : array();
        if(is_array($dbFields)) {
            foreach ($dbFields as $dbFieldName => $dbFieldType) {
                $field = $this->owner->dbObject($dbFieldName);
                if ($this->shouldEncrypt($changedFields, $dbFieldName, $field)) {
                    $this->updateFieldEncryptionKey($field);
                    $field->prepValueForDB($this->owner->$dbFieldName);
                }
            }
        }
    }

    /**
     * Should this value be encrypted?
     * @param array $changedFields
     * @param string $dbFieldName
     * @param DBField $field
     * @return bool
     */
    private function shouldEncrypt($changedFields, $dbFieldName, $field)
    {
        $return = false;
        if (($this->owner->ID === 0 || in_array($dbFieldName, $changedFields)) && $field->is_encrypted) {
            $return = true;
        }
        return $return;
    }

    /**
     * @todo Clean this up. Trusting on the arraymap is not safe
     * @param $property
     * @return mixed
     */
    public function getDecryptedProperty($property)
    {
        $field = $property[1]::create($property[0]);
	$this->updateFieldEncryptionKey($field);
        $record = $this->owner->getField($property[0]);
        $field->setValue($record, array());
        return $field->getValue();
    }

    /**
     * @todo this is not really a readable code.
     * @todo Clean this to make it more readable.
     * @param $bool
     * @return array
     */
    public function allMethodNames($bool)
    {
        $methods = array();
        $classes = Config::inst()->get('EncryptDataObjectFieldsExtension', 'EncryptedDataObjects');
        if (in_array($this->owner->ClassName, $classes)) {
            $fields = Config::inst()->get($this->owner->ClassName, 'db', Config::UNINHERITED);
            foreach ($fields as $fieldName => $fieldType) {
                $reflector = Object::create_from_string($fieldType, '')->is_encrypted;
                if ($reflector === true) {
                    $callFunction = strtolower('get' . $fieldName);
                    self::$db_mapping[$callFunction] = array($fieldName, $fieldType);
                    $methods[] = strtolower('get' . $fieldName);
                }
            }
        }
        return $methods;
    }
	
    /**
     * Checks whether the owner DataObject has a provideEncryptionKey() method and if it does, passes the encryption key
     * returned by that method to $field. This makes the encryption (and decryption) to use a custom key rather than the global ENCRYPT_AT_REST_KEY key.
     *
     * If provideEncryptionKey() returns null, then the field will use the default key instead of a custom one.
     *
     * @param DBField $field The field where the (possible) custom key should be applied to.
     */
    private function updateFieldEncryptionKey(DBField $field)
    {
    	if ($this->owner->hasMethod('provideEncryptionKey'))
	{
	    $field->setEncryptionKey($this->owner->provideEncryptionKey($field->getName(), $field->class));
	}
    }
}