<?php

namespace Madmatt\EncryptAtRest\Traits;

trait EncryptedFieldGetValueTrait
{

    public function getValue(): mixed
    {
        // Type hardening for PHP 8.1+
        $value = (string)$this->value;

        return $this->getDecryptedValue($value);
    }

    public function writeToManipulation(array &$manipulation): void
    {
        if (!method_exists($this, 'prepEncryptedValueForDB')) {
            $manipulation['fields'][$this->name] = $this->exists()
                ? $this->prepValueForDB($this->value) : $this->nullValue();
        } else {
            $manipulation['fields'][$this->name] = $this->exists()
                ? $this->prepEncryptedValueForDB($this->value) : $this->nullValue();
        }
    }

}
