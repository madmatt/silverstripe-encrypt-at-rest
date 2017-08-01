    # silverstripe-encrypt-at-rest

This module allows data to be encrypted in the database, but be decrypted when extracted from the database, using a
secret key (hopefully) known only by the web server.

*Note:* This does not provide significant protection except in the case of database compromise. It should be used as
part of a layered security strategy. This is because the key is still available on the web server, so if remote code
execution is achieved by an attacker, they will be able to read both the database *and* the encryption key, thereby
decrypting the content.

*Note:* This module is not yet ready for real use, it's currently v0.0.1 material.

## Usage

In your DataObject, use EncryptedDBField, to have it encrypted. At this point, everything is stored as text.

Set a key in your `_ss_environment` file. 
 
 ```define('ENCRYPT_AT_REST_KEY', 'mysupersecretlonghexkeyhere1234567890');```
 
### DataObject specific encryption keys
 
Another, optional and advanced way to define the key is to create an optional method in your DataObject class:
 
```PHP
class MyDataObject extends DataObject
{
    private static $db = array(
        'MyEncryptedField' => 'EncryptedText'
    );
    
    public function provideEncryptionKey($field_name, $field_type)
    {
        return *A custom key here*;
    }
}
```

This way you can have multiple keys and you are able to decide which key to use in which situation. You are allowed to return either a Defuse\Crypto\Key object or a plain string presentation of the key. You can also return just `null`, if you want to stick with the default key defined in the `ENCRYPT_AT_REST_KEY` constant. The latter is also used if you do not create the `provideEncryptionKey()` method at all.
 
 `$field_name` and `$field_type` arguments can be used to get to know which field is being currently encrypted/decrypted. The latter argument tells the data type class of the field, for example `EncryptedText`.



## TODO

- Make sure $this->value is _always_ the unencrypted value
- Clean up
- EncryptedEnum needs validation
- Extended testing
- Test if the value is actually encrypted, before trying to decrypt
- 
