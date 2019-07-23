<?php

namespace SGP\IronBox;

use Exception;
use SGP\IronBox\Contracts\Validatable;
use SGP\IronBox\Exceptions\IronBoxException;

class ContainerKeyData implements Validatable
{
    /**
     * @var string
     */
    protected $cipher;

    /**
     * @var string
     */
    protected $symmetricKey;

    /**
     * @var string
     */
    protected $initializationVector;

    /**
     * @var int
     */
    protected $keyStrength;

    /**
     * @var array
     */
    protected $supportedCiphers = [
        1 => 'AES-128-CBC',
        2 => 'AES-256-CBC',
    ];

    /**
     * ContainerKeyData constructor.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $key = lcfirst($key);

            $this->{$key} = $value;
        }
    }

    /**
     * @param string $symmetricKey
     *
     * @return $this
     */
    public function symmetricKey(string $symmetricKey): self
    {
        $this->symmetricKey = $symmetricKey;

        return $this;
    }

    /**
     * @param string $initializationVector
     *
     * @return $this
     */
    public function initializationVector(string $initializationVector): self
    {
        $this->initializationVector = $initializationVector;

        return $this;
    }

    /**
     * @param int $keyStrength
     *
     * @return $this
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     */
    public function keyStrength(int $keyStrength): self
    {
        if ($keyStrength !== 1 && $keyStrength !== 2) {
            throw new IronBoxException('Invalid key strength - must be either 1 or 2');
        }

        $this->keyStrength = $keyStrength;

        $this->setCipher();

        return $this;
    }

    /**
     * Validates that all required fields are present.
     *
     * @return bool
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     */
    public function validate()
    {
        $requiredFields = ['symmetricKey', 'initializationVector', 'keyStrength', 'cipher'];

        foreach ($requiredFields as $requiredField) {
            if (is_null($this->{$requiredField})) {
                throw new IronBoxException("Field `{$requiredField}` is required");
            }
        }

        return true;
    }

    /**
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     */
    protected function setCipher()
    {
        if (! isset($this->supportedCiphers[$this->keyStrength])) {
            throw new IronBoxException('Unsupported cipher');
        }

        $this->cipher = $this->supportedCiphers[$this->keyStrength];
    }

    public function __get($key)
    {
        if (! isset($this->{$key})) {
            throw new Exception("Property `{$key}` doesn't exist");
        }

        return $this->{$key};
    }

    public static function create(array $data = [])
    {
        return new static($data);
    }
}
