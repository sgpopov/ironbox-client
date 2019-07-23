<?php

namespace SGP\IronBox;

use GuzzleHttp\Client;
use SGP\IronBox\Enums\EntityTypes;
use SGP\IronBox\Exceptions\FileNotFound;
use SGP\IronBox\Encryption\FileEncrypter;
use SGP\IronBox\Exceptions\IronBoxException;
use SGP\IronBox\Exceptions\ServiceUnavailable;

class IronBox
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $httpHeaders = [
        'Accept' => 'application/json',
    ];

    /**
     * @var string
     */
    protected $endpoint = 'https://api.goironcloud.com';

    /**
     * @var string
     */
    protected $version = 'latest';

    /**
     * @var string
     */
    protected $email = null;

    /**
     * @var string
     */
    protected $password = null;

    /**
     * @var int
     */
    protected $containerId = null;

    /**
     * IronBox constructor.
     *
     * @param \GuzzleHttp\Client|null $client
     */
    public function __construct(Client $client = null)
    {
        $this->client = $client ?? new Client;

        $this->endpoint = "{$this->endpoint}/{$this->version}/";
    }

    /**
     * @param string $email
     *
     * @return $this
     */
    public function setEmail(string $email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @param string $password
     *
     * @return $this
     */
    public function setPassword(string $password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @param $containerId
     *
     * @return $this
     */
    public function setContainerId($containerId)
    {
        $this->containerId = $containerId;

        return $this;
    }

    /**
     * Checks if the IronBox API server is responding.
     *
     * @return bool
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \SGP\IronBox\Exceptions\ServiceUnavailable
     */
    public function ping()
    {
        $endpoint = $this->endpoint.'Ping';

        $request = $this->client->request('GET', $endpoint);

        if ($request->getStatusCode() !== 200) {
            throw new ServiceUnavailable('IronBox API server is not accessible from this network location');
        }

        return true;
    }

    /**
     * Uploads a given file to an IronBox container.
     *
     * @param string $filePath Local file path of file to upload
     * @param string $blobName Name of the file to use on cloud storage
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \SGP\IronBox\Exceptions\FileNotFound
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     * @throws \SGP\IronBox\Exceptions\ServiceUnavailable
     */
    public function uploadFileToContainer(string $filePath, string $blobName)
    {
        $this->isFile($filePath);

        $encryptedFilePath = $filePath.'.iron';

        $this->ping();

        $keyData = $this->containerKeyData();

        $blobIdName = $this->createEntityContainerBlob($blobName);

        $checkoutData = $this->checkOutEntityContainerBlob($blobIdName);

        (new FileEncrypter($filePath, $encryptedFilePath, $keyData))->encrypt();

        $this->uploadBlob($encryptedFilePath, $checkoutData);

        $this->checkInEntityContainerBlob($blobIdName, $filePath, $checkoutData);

        $this->removeFile($encryptedFilePath);
    }

    /**
     * Fetches an IronBox container key data.
     *
     * @return \SGP\IronBox\ContainerKeyData
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     * @throws \SGP\IronBox\Exceptions\ServiceUnavailable
     */
    public function containerKeyData()
    {
        $endpoint = $this->endpoint.'ContainerKeyData';

        $request = $this->client->request('POST', $endpoint, [
            'headers' => $this->httpHeaders,
            'form_params' => [
                'Entity' => $this->email,
                'EntityType' => EntityTypes::EMAIL_ADDRESS,
                'EntityPassword' => $this->password,
                'ContainerID' => $this->containerId,
            ],
        ]);

        if ($request->getStatusCode() !== 200) {
            throw new ServiceUnavailable('Unable to retrieve container key data');
        }

        $response = json_decode($request->getBody()->getContents(), true);

        if (! $response ||
            ! is_array($response) ||
            ! isset($response['SessionKeyBase64'], $response['SessionIVBase64'], $response['SymmetricKeyStrength'])
        ) {
            throw new IronBoxException('ContainerKeyData call returned invalid data');
        }

        return (new ContainerKeyData)
            ->symmetricKey(base64_decode($response['SessionKeyBase64'], true))
            ->initializationVector(base64_decode($response['SessionIVBase64'], true))
            ->keyStrength($response['SymmetricKeyStrength']);
    }

    /**
     * Creates an IronBox blob in an existing container.
     *
     * @param string $blobName
     *
     * @return string
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     * @throws \SGP\IronBox\Exceptions\ServiceUnavailable
     */
    public function createEntityContainerBlob(string $blobName)
    {
        $endpoint = $this->endpoint.'CreateEntityContainerBlob';

        $request = $this->client->request('POST', $endpoint, [
            'headers' => $this->httpHeaders,
            'form_params' => [
                'Entity' => $this->email,
                'EntityType' => EntityTypes::EMAIL_ADDRESS,
                'EntityPassword' => $this->password,
                'ContainerID' => $this->containerId,
                'BlobName' => $blobName,
            ],
        ]);

        if ($request->getStatusCode() !== 200) {
            throw new ServiceUnavailable('Unable to create entity container blob');
        }

        $response = json_decode($request->getBody()->getContents(), true);

        if (! $response || ! is_string($response)) {
            throw new IronBoxException('CreateEntityContainerBlob call returned invalid data');
        }

        return $response;
    }

    /**
     * Checks outs an entity container blob, so that the caller can begin uploading the contents of the blob.
     *
     * @param string $blobIdName
     *
     * @return \SGP\IronBox\CheckOutData
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     * @throws \SGP\IronBox\Exceptions\ServiceUnavailable
     */
    public function checkOutEntityContainerBlob(string $blobIdName)
    {
        $endpoint = $this->endpoint.'CheckOutEntityContainerBlob';

        $request = $this->client->request('POST', $endpoint, [
            'headers' => $this->httpHeaders,
            'form_params' => [
                'Entity' => $this->email,
                'EntityType' => EntityTypes::EMAIL_ADDRESS,
                'EntityPassword' => $this->password,
                'ContainerID' => $this->containerId,
                'BlobIDName' => $blobIdName,
            ],
        ]);

        if ($request->getStatusCode() !== 200) {
            throw new ServiceUnavailable('Unable to check out entity container blob');
        }

        $response = json_decode($request->getBody()->getContents(), true);

        if (! $response || ! is_array($response)) {
            throw new IronBoxException('CheckOutEntityContainerBlob call returned invalid data');
        }

        $containerKeyData = new CheckOutData($response);

        $containerKeyData->validate();

        return $containerKeyData;
    }

    /**
     * Uploads an encrypted file to cloud storage using the shared access signature provided. This function uploads
     * blocks in 4 MB blocks with max 50k blocks, meaning that there is a 200 GB max for any file uploaded.
     *
     * @param string $encryptedFilePath
     * @param \SGP\IronBox\CheckOutData $checkOutData
     *
     * @return bool
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \SGP\IronBox\Exceptions\FileNotFound
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     */
    public function uploadBlob(string $encryptedFilePath, CheckOutData $checkOutData)
    {
        $this->isFile($encryptedFilePath);
        $checkOutData->validate();

        $blockSizeBytes = 4 * 1024 * 1024;

        // Open handle to encrypted file and send it in blocks
        $sasUriBlockPrefix = $checkOutData->sharedAccessSignatureUri.'&comp=block&blockid=';
        $blockIds = [];

        $i = 0;
        $fh = fopen($encryptedFilePath, 'r');

        while (! feof($fh)) {
            $buf = fread($fh, $blockSizeBytes);

            // block IDs all have to be the same length, which was NOT documented by MSFT
            $blockId = 'block'.str_pad($i, 8, 0, STR_PAD_LEFT);

            // Create a blob block
            $request = $this->client->request('PUT', $sasUriBlockPrefix.base64_encode($blockId), [
                'headers' => [
                    'content-type' => 'application/octet-stream',
                    'x-ms-blob-type' => 'BlockBlob',
                    'x-ms-version' => '2012-02-12',
                ],
                'body' => $buf,
            ]);

            if ($request->getStatusCode() !== 201) {
                throw new IronBoxException('Unable to upload file block');
            }

            // Block was successfuly sent, record its ID
            $blockIds[] = $blockId;

            $i += 1;
        }

        // Done sending blocks, so commit the blocks into a single one
        // Do the final re-assembly on the storage server side

        // build list of block ids as xml elements
        $blockListBody = '';

        foreach ($blockIds as $blockId) {
            $encodedBlockId = trim(base64_encode($blockId));

            // Indicate blocks to commit per 2012-02-12 version PUT block list specifications
            $blockListBody .= sprintf('<Latest>%s</Latest>', $encodedBlockId);
        }

        $request = $this->client->request('PUT', $checkOutData->sharedAccessSignatureUri.'&comp=blockList', [
            'headers' => [
                'content-type' => 'text/xml',
                'x-ms-version' => '2012-02-12',
            ],
            'body' => sprintf('<?xml version="1.0" encoding="utf-8"?><BlockList>%s</BlockList>', $blockListBody),
        ]);

        if ($request->getStatusCode() !== 201) {
            throw new IronBoxException('Unable to upload blob');
        }

        return true;
    }

    /**
     * This method checks-in a checked-out blob indicating to IronBox that this blob is ready.
     * This method should only be called after the caller has finished modifying the checked-out blob.
     *
     * @param string $blobIdName
     * @param string $filePath
     * @param \SGP\IronBox\CheckOutData $checkoutData
     *
     * @return bool
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \SGP\IronBox\Exceptions\FileNotFound
     * @throws \SGP\IronBox\Exceptions\IronBoxException
     * @throws \SGP\IronBox\Exceptions\ServiceUnavailable
     */
    public function checkInEntityContainerBlob(string $blobIdName, string $filePath, CheckOutData $checkoutData)
    {
        $this->isFile($filePath);
        $checkoutData->validate();

        $endpoint = $this->endpoint.'CheckInEntityContainerBlob';

        $request = $this->client->request('POST', $endpoint, [
            'headers' => $this->httpHeaders,
            'form_params' => [
                'Entity' => $this->email,
                'EntityType' => EntityTypes::EMAIL_ADDRESS,
                'EntityPassword' => $this->password,
                'ContainerID' => $this->containerId,
                'BlobIDName' => $blobIdName,
                'BlobSizeBytes' => filesize($filePath),
                'BlobCheckInToken' => $checkoutData->checkInToken,
            ],
        ]);

        if ($request->getStatusCode() !== 200) {
            throw new ServiceUnavailable('Unable to check in entity container blob');
        }

        $response = json_decode($request->getBody()->getContents(), true);

        if ($response !== true) {
            throw new IronBoxException('CheckInEntityContainerBlob call returned invalid data');
        }

        return true;
    }

    /**
     * Determine if the given path is a file.
     *
     * @param string $path
     *
     * @return bool
     *
     * @throws \SGP\IronBox\Exceptions\FileNotFound
     */
    private function isFile(string $path)
    {
        if (is_file($path)) {
            return true;
        }

        throw new FileNotFound("File does not exist at path {$path}");
    }

    /**
     * @param string $path
     *
     * @throws \SGP\IronBox\Exceptions\FileNotFound
     */
    private function removeFile(string $path)
    {
        $this->isFile($path);

        unlink($path);
    }
}
