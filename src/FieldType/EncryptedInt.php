<?php

namespace Madmatt\EncryptAtRest\FieldType;

use Exception;
use Madmatt\EncryptAtRest\Traits\EncryptedFieldGetValueTrait;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Model\ModelData;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBInt;
use Madmatt\EncryptAtRest\AtRestCryptoService;

/**
 * Class EncryptedInt
 * @package EncryptAtRest\Fieldtypes
 *
 * This class wraps around an Int, storing the value in the database as an encrypted string in a varchar field, but
 * returning it to SilverStripe as a decrypted Int object.
 */
class EncryptedInt extends DBInt
{
    use EncryptedFieldGetValueTrait;

    /**
     * @var AtRestCryptoService
     */
    protected $service;

    public function __construct($name = null, $defaultVal = 0)
    {
        parent::__construct($name, $defaultVal);
        $this->service = Injector::inst()->get(AtRestCryptoService::class);
    }

    public function setValue(mixed $value, null|array|ModelData $record = null, bool $markChanged = true): static
    {
        if (is_array($record) && array_key_exists($this->name, $record) && $value === null) {
            $this->value = $record[$this->name];
        } elseif (is_object($record) && property_exists($record, $this->name) && $value === null) {
            $key = $this->name;
            $this->value = $record->$key;
        } else {
            $this->value = $value;
        }

        return $this;
    }

    public function getDecryptedValue(string $value = '')
    {
        // Test if we're actually an encrypted value;
        if (ctype_xdigit($value) && strlen($value) > 130) {
            try {
                return (int)$this->service->decrypt($value);
            } catch (Exception $e) {
                // We were unable to decrypt. Possibly a false positive, but return the unencrypted value
                return (int)$value;
            }
        }
        return (int)$value;
    }

    public function requireField(): void
    {
        $values = array(
            'type'  => 'text',
            'parts' => array(
                'datatype'   => 'text',
                'null'       => 'not null',
                'arrayValue' => $this->arrayValue
            )
        );

        DB::require_field($this->tableName, $this->name, $values);
    }

    public function prepEncryptedValueForDB(mixed $value): string
    {
        $value = parent::prepValueForDB($value);
        $ciphertext = $this->service->encrypt($value);
        $this->value = $ciphertext;
        return $ciphertext;
    }

}
