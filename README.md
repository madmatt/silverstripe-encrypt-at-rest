# silverstripe-encrypt-at-rest

This module allows data to be encrypted, in the database, using a secret key (hopefully) known only by the web server.

Usage examples:

`$object->dbObject('SecureText')->getDecryptedValue();`

or (in templates)

`$Object.SecureText.DecryptedValue`

---

## Protection Notice

**This module does not garuntee the security of your data. Only use this as a protection measure if you have consulted with a data protection expert. In most cases, encrypting the entire database is adequate. Only use this module to encrypt data at-rest (on a field-by-field basis) if your layered protection strategy requires and accomodates it.**

This is because the key is still available on the web server, so if remote code execution is achieved by an attacker, they will be able to read both the database *and* the encryption key, thereby decrypting the content.

## Performance Notice

Encrypting/Decrypting data per field has a performance overhead, which may produce undesirable results in your project.

This module is **not yet ready for real use**, it's currently v0.0.1 material.

---

## Installation

**Requires**

SilverStripe >= 4.1

**Composer**

```
composer require madmatt/silverstripe-encrypt-at-rest
```

**Setup key**

1. Generate a hex key with `vendor/bin/generate-defuse-key` (tool supplied by `defuse/php-encryption`). _Key must be based on a >=36 character ASCII string._
2. Set the key in your environment variables under the variable `ENCRYPT_AT_REST_KEY`

_For non-public environments you can set this in your `.env` e.g_
```
ENCRYPT_AT_REST_KEY="{generated defuse key}"
```

For more information view SilverStripe [Environment Management](https://docs.silverstripe.org/en/4/getting_started/environment_management/).

## Usage

In your DataObject, use an encrypted field type.

E.g

```
class MyDataObject extends DataObject {

    private static $db = [
        'SecureText' => 'EncryptedText'
    ];

}
```

Available field types include

- EncryptedDatetime
- EncryptedDecimal
- EncryptedEnum
- EncryptedInt
- EncryptedText
- EncryptedVarchar

Data will be encrypted when values are comitted to the database.

To use decrypted values, use the following...

Back-end:

`$object->dbObject('SecureText')->getDecryptedValue();`

Silverstripe template:

`$Object.SecureText.DecryptedValue`

## TODO

- Descrypt values when DataObjects are hydrated
- Clean up
- EncryptedEnum needs validation
- Extended testing
- Test if the value is actually encrypted, before trying to decrypt
- Add CI
- 
