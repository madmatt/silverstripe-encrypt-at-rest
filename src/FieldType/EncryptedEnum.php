<?php

namespace Madmatt\EncryptAtRest\FieldType;

use Exception;
use Madmatt\EncryptAtRest\Traits\EncryptedFieldGetValueTrait;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\FieldValidation\OptionFieldValidator;
use SilverStripe\Model\ModelData;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBEnum;
use Madmatt\EncryptAtRest\AtRestCryptoService;

/**
 * Class EncryptedEnum
 * @package EncryptAtRest\Fieldtypes
 *
 * This class wraps around a Enum, storing the value in the database as an encrypted string in a varchar field, but
 * returning it to SilverStripe as a decrypted Enum object.
 */
class EncryptedEnum extends DBEnum
{
    use EncryptedFieldGetValueTrait;

    /**
     * @var AtRestCryptoService
     */
    protected $service;

    /**
     * Disable validation added in CMS6 but todo in future release
     */
    private static array $field_validators = [
        OptionFieldValidator::class => null,
    ];

    public function __construct($name = null, $enum = null, $default = 0, $options = [])
    {
        parent::__construct($name, $enum, $default, $options);

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
                return $this->service->decrypt($value);
            } catch (Exception $e) {
                // We were unable to decrypt. Possibly a false positive, but return the unencrypted value
                return $value;
            }
        }
        return $value;
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

    public function prepValueForDB(mixed $value): array|string|null
    {
        $value = parent::prepValueForDB($value);
        $ciphertext = $this->service->encrypt($value);
        $this->value = $ciphertext;
        return $ciphertext;
    }
}
