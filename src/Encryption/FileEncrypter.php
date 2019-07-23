<?php

namespace SGP\IronBox\Encryption;

use Exception;
use SGP\IronBox\ContainerKeyData;
use SGP\IronBox\Exceptions\IronBoxException;

class FileEncrypter
{
    /**
     * @var string
     */
    protected $inputFile;

    /**
     * @var string
     */
    protected $outputFile;

    /**
     * @var \SGP\IronBox\Encryption\Encrypter
     */
    protected $encrypter;

    public function __construct($inputFile, $outputFile, ContainerKeyData $containerKeyData)
    {
        $this->inputFile = $inputFile;
        $this->outputFile = $outputFile;

        $containerKeyData->validate();

        $this->encrypter = new Encrypter(
            $containerKeyData->symmetricKey,
            $containerKeyData->cipher,
            $containerKeyData->initializationVector
        );
    }

    /**
     * Encrypts a file using the symmetric key data.
     *
     * @return bool
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     */
    public function encrypt()
    {
        try {
            $readBlockSize = 1024;

            $in = fopen($this->inputFile, 'rb');
            $out = fopen($this->outputFile, 'wb');

            while (! feof($in)) {
                $line = fread($in, $readBlockSize);

                if (strlen($line) < $readBlockSize) {
                    $line = $this->pad($line, $readBlockSize);
                }

                fwrite($out, $this->encrypter->encrypt($line));
            }

            fclose($in);

            fclose($out);

            return true;
        } catch (Exception $e) {
            throw new IronBoxException('Unable to encrypt local copy of file', 0, $e);
        }
    }

    /**
     * Pad a string to a certain length with another string.
     *
     * @param string $input
     * @param int $size
     *
     * @return string
     */
    private function pad(string $input, int $size)
    {
        return str_pad($input, strlen($input) + ($size - strlen($input) % $size), chr(16 - strlen($input) % $size));
    }
}
