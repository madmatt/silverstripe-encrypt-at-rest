<?php

namespace Madmatt\EncryptAtRest;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\File;
use Defuse\Crypto\Key;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Assets\FilenameParsing\ParsedFileID;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;

class AtRestCryptoService
{

    private const FILE_EXTENSION = '.enc';

    /**
     * @param string   $raw
     * @param null|Key $key
     *
     * @return string
     * @throws EnvironmentIsBrokenException
     * @throws BadFormatException
     */
    public function encrypt($raw, $key = null)
    {
        $key = $this->getKey($key);
        return Crypto::Encrypt((string)$raw, $key);
    }

    /**
     * @param string   $ciphertext
     * @param null|Key $key
     *
     * @return string
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     * @throws WrongKeyOrModifiedCiphertextException
     */
    public function decrypt($ciphertext, $key = null)
    {
        $key = $this->getKey($key);
        return Crypto::Decrypt($ciphertext, $key);
    }

    /**
     * @param \SilverStripe\Assets\File $file
     * @param null|Key                  $key
     *
     * @return false|\SilverStripe\Assets\File
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     */
    public function encryptFile($file, $key = null, $visibility = AssetStore::VISIBILITY_PROTECTED)
    {
        $key = $this->getKey($key);
        try {
            $currentPath = $this->getFullPath($file, $visibility);
            $encryptedFilename = $currentPath . self::FILE_EXTENSION;
            File::encryptFile($currentPath, $encryptedFilename, $key);
            $filename = $file->getFilename() . self::FILE_EXTENSION;
            $isDeleted = $file->deleteFile();
            $file->File->setField('Filename', $filename);
            $file->write();

            if ($visibility === AssetStore::VISIBILITY_PROTECTED) {
                $file->protectFile();
            } elseif ($visibility === AssetStore::VISIBILITY_PUBLIC) {
                $file->publishFile();
            }

            if (!$isDeleted && file_exists($currentPath)) {
                @unlink($currentPath);
            }

            return $file;
        } catch (Exception $e) {
            Injector::inst()->get(LoggerInterface::class)
                ->error(sprintf('Encryption exception while parsing "%s": %s', $file->Name, $e->getMessage()));
            return false;
        }
    }

    /**
     * @param \SilverStripe\Assets\File    $file
     * @param null|Key $key
     *
     * @return false|\SilverStripe\Assets\File
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     */
    public function decryptFile($file, $key = null, $visibility = AssetStore::VISIBILITY_PROTECTED)
    {
        $key = $this->getKey($key);
        try {
            $currentPath = $this->getFullPath($file, $visibility);

            if (!$this->str_ends_with($currentPath, self::FILE_EXTENSION)) {
                throw new InvalidArgumentException(sprintf(
                    'The file located at %s, does not end in %s',
                    $currentPath,
                    self::FILE_EXTENSION
                ));
            }

            $decryptedFilepath = str_replace(self::FILE_EXTENSION, '', $currentPath);
            File::decryptFile($currentPath, $decryptedFilepath, $key);
            $isDeleted = $file->deleteFile();
            $newFileName = str_replace(self::FILE_EXTENSION, '', $file->getFilename());
            $file->File->setField('Filename', $newFileName);
            $file->write();

            if ($visibility === AssetStore::VISIBILITY_PROTECTED) {
                $file->protectFile();
            } elseif ($visibility === AssetStore::VISIBILITY_PUBLIC) {
                $file->publishFile();
            }

            if (!$isDeleted && file_exists($currentPath)) {
                @unlink($currentPath);
            }

            return $file;
        } catch (Exception $e) {
            Injector::inst()->get(LoggerInterface::class)
                ->error(sprintf('Decryption exception while parsing "%s": %s', $file->Name, $e->getMessage()));
            return false;
        }
    }

    /**
     * @param $rawKey
     *
     * @return Key
     * @throws BadFormatException
     * @throws EnvironmentIsBrokenException
     */
    public function getKey($rawKey)
    {
        // If this is already a \Defuse\Crypto\Key object, just return it
        if ($rawKey instanceof Key) {
            return $rawKey;
        }

        $envKey = Environment::getEnv('ENCRYPT_AT_REST_KEY');
        if ($rawKey === null && $envKey) {
            // Retrieve key from _ss_env, if set
            $rawKey = $envKey;
        }

        if ($rawKey === null) {
            throw new InvalidArgumentException('Can\'t encrypt without a key. Define ENCRYPT_AT_REST_KEY, or pass the $key argument.');
        }

        return Key::LoadFromAsciiSafeString($rawKey);
    }


    /**
     * @param \SilverStripe\Assets\File $file
     * @param string $visibility
     * @return mixed
     * @throws ReflectionException
     */
    protected function getFullPath($file, $visibility = AssetStore::VISIBILITY_PROTECTED)
    {
        $assetStore = Injector::inst()->get(AssetStore::class);

        $adapter = $visibility === AssetStore::VISIBILITY_PROTECTED
            ? $assetStore->getProtectedFilesystem()->getAdapter()
            : $assetStore->getPublicFilesystem()->getAdapter();

        $strategy = $visibility === AssetStore::VISIBILITY_PROTECTED
            ? $assetStore->getProtectedResolutionStrategy()
            : $assetStore->getPublicResolutionStrategy();

        $parsedFileID = new ParsedFileID($file->getFilename(), $file->getHash(), $file->getVariant());

        try {
            $fileID = $strategy->buildFileID($parsedFileID);
        } catch (Exception $e) {
            $fileID = rtrim($filename, '\\/');
        }

        return $adapter->applyPathPrefix($fileID);
    }

    /**
     * @deprecated use https://www.php.net/manual/en/function.str-ends-with.php
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    private function str_ends_with(string $haystack, string $needle): bool
    {
        $needleLength = strlen($needle);

        if ($needleLength === 0) {
            return false;
        }

        return 0 === substr_compare($haystack, $needle, -$needleLength);
    }
}
