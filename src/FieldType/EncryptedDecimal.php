<?php

namespace Madmatt\EncryptAtRest\FieldType;

use Exception;
use Madmatt\EncryptAtRest\Traits\EncryptedFieldGetValueTrait;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDecimal;
use Madmatt\EncryptAtRest\AtRestCryptoService;

/**
 * Class EncryptedDatetime
 * @package EncryptAtRest\Fieldtypes
 *
 * This class wraps around a SS_Datetime, storing the value in the database as an encrypted string in a varchar field, but
 * returning it to SilverStripe as a decrypted SS_Datetime object.
 */
class EncryptedDecimal extends DBDecimal
{
    use EncryptedFieldGetValueTrait;

    /**
     * @var AtRestCryptoService
     */
    protected $service;

    public function __construct($name = null, $wholeSize = 9, $decimalSize = 2, $defaultValue = 0)
    {
        parent::__construct($name, $wholeSize, $decimalSize, $defaultValue);
        $this->service = Injector::inst()->get(AtRestCryptoService::class);
    }

    public function setValue($value, $record = null, $markChanged = true)
    {
        if (is_array($record) && array_key_exists($this->name, $record) && $value === null) {
            $this->value = $record[$this->name];
        } elseif (is_object($record) && property_exists($record, $this->name) && $value === null) {
            $key = $this->name;
            $this->value = $record->$key;
        } else {
            $this->value = $value;
        }
    }

    public function getDecryptedValue(string $value = '')
    {
        // Test if we're actually an encrypted value;
        if (ctype_xdigit($value) && strlen($value) > 130) {
            try {
                $value = $this->service->decrypt($value);
            } catch (Exception $e) {
                // We were unable to decrypt. Possibly a false positive, but return the unencrypted value
                return $value;
            }
        }
        return (float)$value;
    }

    public function requireField()
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

    public function prepValueForDB($value)
    {
        $value = parent::prepValueForDB($value);
        $ciphertext = $this->service->encrypt($value);
        $this->value = $ciphertext;
        return $ciphertext;
    }
}
