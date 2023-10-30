<?php

namespace Madmatt\EncryptAtRest\Tests;

use Madmatt\EncryptAtRest\AtRestCryptoService;
use SilverStripe\Assets\File;
use SilverStripe\Assets\FilenameParsing\ParsedFileID;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class AtRestCryptoServiceTest extends SapphireTest
{

    protected $usesDatabase = true;

    public function testEncrypt()
    {
        /** @var AtRestCryptoService $service */
        $service = Injector::inst()->get(AtRestCryptoService::class);

        $originalText = 'This is a test string';
        $encrypted1 = $service->encrypt($originalText);
        $encrypted2 = $service->encrypt($originalText);

        $this->assertNotEquals($originalText, $encrypted1);
        $this->assertNotEquals($originalText, $encrypted2);
        $this->assertNotEquals($encrypted1, $encrypted2); // This is technically testing a property of the defuse lib
    }

    public function testDecrypt()
    {
        /** @var AtRestCryptoService $service */
        $service = Injector::inst()->get(AtRestCryptoService::class);

        $expectedInt = 200;
        $expectedFloat = 295.285;
        $expectedString = 'This is a test string';

        $encrypted1Int = $service->encrypt($expectedInt);
        $encrypted1Float = $service->encrypt($expectedFloat);
        $encrypted1String = $service->encrypt($expectedString);

        $this->assertEquals($expectedInt, $service->decrypt($encrypted1Int));
        $this->assertEquals($expectedFloat, $service->decrypt($encrypted1Float));
        $this->assertEquals($expectedString, $service->decrypt($encrypted1String));

        // Ensure that when we encrypt the same value twice, we can still decrypt
        $encrypted2Int = $service->encrypt($expectedInt);
        $encrypted2Float = $service->encrypt($expectedFloat);
        $encrypted2String = $service->encrypt($expectedString);

        $this->assertNotEquals($encrypted1Int, $encrypted2Int);
        $this->assertNotEquals($encrypted1Float, $encrypted2Float);
        $this->assertNotEquals($encrypted1String, $encrypted2String);

        $this->assertEquals($expectedInt, $service->decrypt($encrypted2Int));
        $this->assertEquals($expectedFloat, $service->decrypt($encrypted2Float));
        $this->assertEquals($expectedString, $service->decrypt($encrypted2String));
    }

    /**
     * @dataProvider dataEncryptFile
     */
    public function testEncryptFile($filename, $contents, $visibility)
    {
        $originalText = $contents;
        $originalFilename = $filename;
        $assetStore = Injector::inst()->get(AssetStore::class);
        $strategy = $visibility === AssetStore::VISIBILITY_PROTECTED
            ? $assetStore->getProtectedResolutionStrategy()
            : $assetStore->getPublicResolutionStrategy();
        $adapter = $visibility === AssetStore::VISIBILITY_PROTECTED
            ? $assetStore->getProtectedFilesystem()->getAdapter()
            : $assetStore->getPublicFilesystem()->getAdapter();

        $file = File::create();
        $file->setFromString($originalText, $originalFilename);
        $file->write();

        if ($visibility === AssetStore::VISIBILITY_PROTECTED) {
            $file->protectFile();
        } elseif ($visibility === AssetStore::VISIBILITY_PUBLIC) {
            $file->publishFile();
        }

        $oldFilename = $adapter->prefixPath(
            $strategy->buildFileID(
                new ParsedFileID(
                    $file->getFilename(),
                    $file->getHash(),
                    $file->getVariant()
                )
            )
        );

        // Confirm file exists
        $this->assertFileExists($oldFilename);
        $this->assertTrue(ctype_print($file->getString()));
        $this->assertEquals($originalText, $file->getString());
        $this->assertEquals($originalFilename, $file->Name);
        $this->assertEquals($originalFilename, $file->getFilename());

        if ($visibility === AssetStore::VISIBILITY_PROTECTED) {
            $this->assertStringContainsString('assets/.protected/', $oldFilename);
        } elseif ($visibility === AssetStore::VISIBILITY_PUBLIC) {
            $this->assertStringNotContainsString('assets/.protected/', $oldFilename);
        }

        /** @var AtRestCryptoService $service */
        $service = Injector::inst()->get(AtRestCryptoService::class);
        $encryptedFile = $service->encryptFile($file, null, $visibility);
        $this->assertEquals($file->Name, $encryptedFile->Name);
        $this->assertEquals($originalFilename . '.enc', $encryptedFile->getFilename());

        // Confirm the old file has been deleted
        $this->assertFileDoesNotExist($oldFilename);

        $encryptedFilename = $adapter->prefixPath(
            $strategy->buildFileID(
                new ParsedFileID(
                    $encryptedFile->getFilename(),
                    $encryptedFile->getHash(),
                    $encryptedFile->getVariant()
                )
            )
        );

        // Confirm the new file exists
        $this->assertFileExists($encryptedFilename);

        if ($visibility === AssetStore::VISIBILITY_PROTECTED) {
            $this->assertStringContainsString('assets/.protected/', $encryptedFilename);
        } elseif ($visibility === AssetStore::VISIBILITY_PUBLIC) {
            $this->assertStringNotContainsString('assets/.protected/', $encryptedFilename);
        }

        $encryptedFileString = $encryptedFile->getString() ?: '';
        // Confirm the new file is encrypted
        $this->assertFalse(ctype_print($encryptedFileString));
        $this->assertNotEquals($originalText, $encryptedFileString);
        $this->assertEquals($originalFilename, $encryptedFile->Name);
        $this->assertEquals($originalFilename . '.enc', $file->getFilename());

        // Now decrypt the file back
        $decryptedFile = $service->decryptFile($encryptedFile, null, $visibility);
        $decryptedFilename = $adapter->prefixPath(
            $strategy->buildFileID(
                new ParsedFileID(
                    $decryptedFile->getFilename(),
                    $decryptedFile->getHash(),
                    $decryptedFile->getVariant()
                )
            )
        );

        // Confirm that old file has been recreated
        $this->assertFileExists($oldFilename);
        $this->assertEquals($oldFilename, $decryptedFilename);
        $this->assertEquals($originalFilename, $decryptedFile->Name);
        $this->assertEquals($originalFilename, $decryptedFile->getFilename());

        if ($visibility === AssetStore::VISIBILITY_PROTECTED) {
            $this->assertStringContainsString('assets/.protected/', $decryptedFilename);
        } elseif ($visibility === AssetStore::VISIBILITY_PUBLIC) {
            $this->assertStringNotContainsString('assets/.protected/', $decryptedFilename);
        }

        // Confirm that original text has been decoded properly
        $this->assertEquals($originalText, $decryptedFile->getString());

        // Confirm that encrypted file has been deleted
        $this->assertFileDoesNotExist($encryptedFilename);
    }

    /**
     * @see testEncryptFile
     */
    public function dataEncryptFile()
    {
        return [
            ['test-public-filename.txt', 'This is a test file', AssetStore::VISIBILITY_PUBLIC],
            ['test-protect-filename.txt', 'This is a test file', AssetStore::VISIBILITY_PROTECTED],
        ];
    }

}
