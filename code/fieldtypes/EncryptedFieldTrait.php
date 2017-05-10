<?php

/**
 * Trait EncryptedFieldTrait
 *
 * This is a trait shared by all EncryptedXXX data field classes. Currently it only contains methods and properties for an ability to define custom encryption keys at DataObject level, but perhaps the trait can be extended to cover all other methods that are currently duplicated in the EncryptedXXX classes.
 */
trait EncryptedFieldTrait
{
	/**
	 * @var string|Defuse\Crypto\Key|null An encryption key that will override the global ENCRYPT_AT_REST_KEY key.
	 * @see self::getEncryptionKey()
	 */
	private $encryption_key = null;
	
	/**
	 * Returns a custom key if one is explicitly set for this field. If a key is not set using setEncryptionKey(),
	 * returns null, which will trigger the AtRestCryptoService to use the default global key set in the ENCRYPT_AT_REST_KEY
	 * constant.
	 *
	 * @return string|Defuse\Crypto\Key|null
	 */
	public function getEncryptionKey()
	{
		return $this->encryption_key;
	}
	
	/**
	 * Sets an encryption key that will override the default key defined in the ENCRYPT_AT_REST_KEY constant.
	 * @see self::getEncryptionKey()
	 * @param string|Defuse\Crypto\Key|null $encryption_key
	 */
	public function setEncryptionKey($encryption_key)
	{
		$this->encryption_key = $encryption_key;
	}
	
	/**
	 * Uses a DataObject or $record array to retrieve a custom encryption/decryption key by calling that DataObject's provideEncryptionKey() method. If the method is not defined, does not alter the key which will be used.
	 *
	 * @param array|DataObject $record
	 */
	private function setEncryptionKeyFromRecord($record)
	{
		if (is_object($record))
		{
			$data_object = $record;
		}
		else
		{
			//Create a DataObject from a $record array
			if (empty($record) || !isset($record['ClassName']) || !isset($record['ID'])) return;
			$class = $record['ClassName'];
			if ($id = $record['ID'])
			{
				//Use the specific DataObject instance to provide the key
				$data_object = DataObject::get_by_id($class, $id);
			}
			else
			{
				//No specific DataObject is defined (probably we are working on a new DataObject which is not yet written to the database), so just create a generic instance of it's class as that's the closest we can get.
				$data_object = singleton($class);
			}
		}
		if ($data_object->hasMethod('provideEncryptionKey'))
		{
			//Retrieve a custom key from the DataObject.
			$this->setEncryptionKey($data_object->provideEncryptionKey($this->getName(), $this->class));
		}
		else
		{
			//The DataObject does not have a method that would provide the custom key, so do nothing.
		}
	}
}