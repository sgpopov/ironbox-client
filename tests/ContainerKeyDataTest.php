<?php

namespace SGP\IronBox\Tests;

use SGP\IronBox\ContainerKeyData;
use PHPUnit\Framework\TestCase;
use SGP\IronBox\Exceptions\IronBoxException;

class ContainerKeyDataTest extends TestCase
{
    /**
     * @test
     * @covers \SGP\IronBox\ContainerKeyData::validate
     */
    public function is_should_validate_required_fields()
    {
        $this->expectException(IronBoxException::class);

        (new ContainerKeyData)->validate();

        $containerKeyData = new ContainerKeyData([
            'symmetricKey' => 1,
            'initializationVector' => 1,
            'keyStrength' => 1,
        ]);

        $this->assertTrue($containerKeyData->validate());
    }
    
    /**
     * @test
     * @covers \SGP\IronBox\ContainerKeyData::keyStrength
     */
    public function it_should_validate_key_strength()
    {
        $this->expectException(IronBoxException::class);
        $this->expectExceptionMessage('Invalid key strength - must be either 1 or 2');

        (new ContainerKeyData)->keyStrength(0);

        $this->assertInstanceOf(
            ContainerKeyData::class,
            (new ContainerKeyData)->keyStrength(1)
        );

        $this->assertInstanceOf(
            ContainerKeyData::class,
            (new ContainerKeyData)->keyStrength(2)
        );
    }

    /**
     * @test
     * @covers \SGP\IronBox\ContainerKeyData::keyStrength
     * @covers \SGP\IronBox\ContainerKeyData::setCipher
     * @covers \SGP\IronBox\ContainerKeyData::__get
     */
    public function it_should_set_correct_cipher()
    {
        $containerKeyData = new ContainerKeyData;
        $containerKeyData->keyStrength(1);

        $this->assertEquals('AES-128-CBC', $containerKeyData->cipher);

        $containerKeyData = new ContainerKeyData;
        $containerKeyData->keyStrength(2);

        $this->assertEquals('AES-256-CBC', $containerKeyData->cipher);
    }
}
