<?php

namespace SGP\IronBox\Tests;

use SGP\IronBox\CheckOutData;
use PHPUnit\Framework\TestCase;
use SGP\IronBox\Exceptions\IronBoxException;

class CheckOutDataTest extends TestCase
{
    /**
     * @test
     * @covers \SGP\IronBox\CheckOutData::validate
     */
    public function is_should_validate_required_fields()
    {
        $this->expectException(IronBoxException::class);
        $this->expectExceptionMessage('Field `sharedAccessSignature` is required');

        (new CheckOutData)->validate();

        $checkOutData = new CheckOutData([
            'sharedAccessSignature' => 123,
            'sharedAccessSignatureUri' => 123,
            'checkInToken' => 123,
            'storageUri' => 123,
            'storageType' => 123,
            'containerStorageName' => 123,
        ]);

        $this->assertTrue($checkOutData->validate());
    }

    /**
     * @test
     * @covers \SGP\IronBox\CheckOutData::__get
     */
    public function it_should_return_property_()
    {
        $checkOutData = new CheckOutData([
            'sharedAccessSignature' => 1,
            'sharedAccessSignatureUri' => 2,
            'checkInToken' => 3,
            'storageUri' => 4,
            'storageType' => 5,
            'containerStorageName' => 6,
        ]);

        $this->assertEquals(1, $checkOutData->sharedAccessSignature);
        $this->assertEquals(2, $checkOutData->sharedAccessSignatureUri);
        $this->assertEquals(3, $checkOutData->checkInToken);
        $this->assertEquals(4, $checkOutData->storageUri);
        $this->assertEquals(5, $checkOutData->storageType);
        $this->assertEquals(6, $checkOutData->containerStorageName);

        $this->expectException(IronBoxException::class);
        $this->expectExceptionMessage("Property `nonExistingProperty` doesn't exist");

        $checkOutData->nonExistingProperty;
    }
}
