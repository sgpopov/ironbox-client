<?php

namespace SGP\IronBox;

use SGP\IronBox\Contracts\Validatable;
use SGP\IronBox\Exceptions\IronBoxException;

class CheckOutData implements Validatable
{
    /**
     * @var string
     */
    protected $sharedAccessSignature;

    /**
     * @var string
     */
    protected $sharedAccessSignatureUri;

    /**
     * @var string
     */
    protected $checkInToken;

    /**
     * @var string
     */
    protected $storageUri;

    /**
     * @var int
     */
    protected $storageType;

    /**
     * @var string
     */
    protected $containerStorageName;

    /**
     * CheckOutData constructor.
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
     * Validates that all required fields are present
     *
     * @return bool
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     */
    public function validate()
    {
        $requiredFields = [
            'sharedAccessSignature', 'sharedAccessSignatureUri', 'checkInToken',
            'storageUri', 'storageType', 'containerStorageName',
        ];

        foreach ($requiredFields as $requiredField) {
            if (is_null($this->{$requiredField})) {
                throw new IronBoxException("Field `{$requiredField}` is required");
            }
        }

        return true;
    }

    public function __get($key)
    {
        if (! isset($this->{$key})) {
            throw new IronBoxException("Property `{$key}` doesn't exist");
        }

        return $this->{$key};
    }

    public static function create(array $data = [])
    {
        return new static($data);
    }
}
