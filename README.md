# silverstripe-encrypt-at-rest

![github actions](https://github.com/madmatt/silverstripe-encrypt-at-rest/actions/workflows/main.yml/badge.svg)

This module allows Silverstripe CMS ORM data to be encrypted before being stored in the database, and automatically decrypted before using within your application. To do this, we use a secret key known only by the web server.


## Caveats to understand
* It's important to note that this module does not guarantee the security of your data completely. You should only use this as a protection measure if you fully understand how the module operates. In most cases, encrypting the entire database is both adequate and similarly effective. Only use this module to encrypt data at-rest (on a field-by-field basis) if your layered protection strategy requires and accomodates it. To be clear - when encrypting data at rest, the data must be decrypted before being used. In almost all cases, the web server hosting the website is far more accessible to attacks than the the database server, meaning that an attacker who can compromise your web server will have access to both the database and the encryption key used to encrypt the data.
* Encrypting and decrypting data on a field-by-field basis has a performance overhead, which may produce undesirable results in your project.
* This module uses the `defuse/php-encryption` library under the hood, which prefers strong security over performance. Encrypting lots of fields on a `DataObject` can significantly slow down any operations that read or write large amounts of data (for example `ModelAdmin` views in the CMS that render 50+ records at once can take many seconds of processing power just to decrypt fields). Care should be taken to ensure only the minimal set of data is encrypted, and that this data does not need to be used frequently.


## Requirements
* SilverStripe CMS 4.9

## Installation
Install via Composer:

```
composer require madmatt/silverstripe-encrypt-at-rest
```

Once installed, you need to generate an encryption key that will be used to encrypt all data.

1. Generate a hex key with `vendor/bin/generate-defuse-key` (tool supplied by `defuse/php-encryption`). This will output a ASCII-safe key that starts with `def`.
2. Set this key as the environment variable `ENCRYPT_AT_REST_KEY`.

For development environments you can set this in your `.env` e.g:

```
ENCRYPT_AT_REST_KEY="{generated defuse key}"
```

For more information view SilverStripe [Environment Management](https://docs.silverstripe.org/en/4/getting_started/environment_management/).

## Usage

In your `DataObject`, create new database fields using an encrypted field type. **Note:** It's not supported to convert an existing field that has data into an encrypted field. This _might_ work but is not guaranteed. You should migrate your data by creating a new field, and creating a task to map old fields to new encrypted fields if necessary.

For example:

```php
use Madmatt\EncryptAtRest\FieldType\EncryptedVarchar;

class SecureDataObject extends DataObject {

    private static $db = [
        'NormalText' => 'Varchar'
        'SecureText' => EncryptedVarchar::class
    ];

}
```

See the `src/FieldType` folder for all field types, or review the below list:

- `Madmatt\EncryptAtRest\FieldType\EncryptedDatetime`
- `Madmatt\EncryptAtRest\FieldType\EncryptedDecimal`
- `Madmatt\EncryptAtRest\FieldType\EncryptedEnum`
- `Madmatt\EncryptAtRest\FieldType\EncryptedInt`
- `Madmatt\EncryptAtRest\FieldType\EncryptedText`
- `Madmatt\EncryptAtRest\FieldType\EncryptedVarchar`

**Note:** When saving in the database, all of these encrypted fields are stored as `TEXT` column types. This is due to the length of the encrypted data being generally much longer than the original text string. They do not take up table column space, but result in longer query execution times when many fields are included as the database needs to go retrieve all these fields from separate blob storage.

**Note 2:** These fields all extend from the base data type (e.g. `EncryptedDatetime extends DBDatetime`) so most common field helper methods can be used (e.g. `$DatetimeField.Ago`).

Data will be automatically encrypted when values are written to the database, and decrypted whenever that data is read back from the database.

To use decrypted values, you just use the value like you would in any other context. For example:

```php
// Via DataObject::get()
$obj = SecureDataObject::get()->first()->SecureText; // Returns the decrypted string from the field

// Getting the DB field
$obj = SecureDataObject::get()->first();
$field = $obj->dbObject('SecureText'); // Returns an EncryptedVarchar object
$uppercase = $field->UpperCase(); // Method on DBString, returns a string
```

Usage within Silverstripe templates is also straightforward:

```html
<% loop $SecureDataObjects %>
    <p>$SecureText.UpperCase</p>
<% end_loop %>
```

If you've use the Silverstripe CMS 3 version of this module, you no longer need to rely on the `->getDecryptedValue()` method - the value will always be decrypted when accessing it.

### Encrypting and decrypting arbitrary text strings and files without using the ORM
You can also encrypt/decrypt arbitrary text strings as well as entire files on the filesystem without using the Silverstripe ORM (e.g. without using `DataObject`). You might want to do this to securely communicate with an API for example.

```php
use Madmatt\EncryptAtRest\AtRestCryptoService;
use SilverStripe\Assets\File;
use SilverStripe\Core\Injector\Injector;

$text = 'This is an unencrypted string!';

/** @var AtRestCryptoService $service */
$service = Injector::inst()->get(AtRestCryptoService::class);
$encryptedText = $service->encrypt($text); // Returns encrypted string starting with `def`
$unencryptedText = $service->decrypt($encryptedText); // Returns 'This is an unencrypted string!'

$file = File::get()->byID(1); // Presume this is a file that contains the text string above

// This will encrypt the file contents, delete the original file from the filesystem and create a new file at the same path with .enc appended to the filename
$encryptedFile = $service->encryptFile($file);

// This will decrypt the file contents, delete the encrypted file from the filesystem and create a new file at the same path with .enc stripped from the filename
$decryptedFile = $service->decryptFile($encryptedFile);
```
