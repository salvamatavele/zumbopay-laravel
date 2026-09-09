<?php

namespace ZumboPay\Tests;

use PHPUnit\Framework\TestCase;
use ZumboPay\Enums\Channel;
use ZumboPay\Support\PhoneNormalizer;

class PhoneNormalizerTest extends TestCase
{
    public function test_normalizes_local_9_digit_number(): void
    {
        $this->assertEquals('258841234567', PhoneNormalizer::normalize('841234567'));
        $this->assertEquals('258861234567', PhoneNormalizer::normalize('861234567'));
        $this->assertEquals('258821234567', PhoneNormalizer::normalize('821234567'));
    }

    public function test_normalizes_international_format(): void
    {
        $this->assertEquals('258841234567', PhoneNormalizer::normalize('+258 84 123 4567'));
        $this->assertEquals('258861234567', PhoneNormalizer::normalize('258-86-123-4567'));
        $this->assertEquals('258821234567', PhoneNormalizer::normalize('+258821234567'));
    }

    public function test_validates_mozambican_numbers(): void
    {
        $this->assertTrue(PhoneNormalizer::isValid('841234567'));
        $this->assertTrue(PhoneNormalizer::isValid('851234567'));
        $this->assertTrue(PhoneNormalizer::isValid('861234567'));
        $this->assertTrue(PhoneNormalizer::isValid('871234567'));
        $this->assertTrue(PhoneNormalizer::isValid('821234567'));
        $this->assertTrue(PhoneNormalizer::isValid('831234567'));

        $this->assertFalse(PhoneNormalizer::isValid('811234567')); // Invalid prefix
        $this->assertFalse(PhoneNormalizer::isValid('12345'));     // Too short
        $this->assertFalse(PhoneNormalizer::isValid(''));
    }

    public function test_detects_channels_correctly(): void
    {
        $this->assertEquals(Channel::Mpesa, PhoneNormalizer::detectChannel('841234567'));
        $this->assertEquals(Channel::Mpesa, PhoneNormalizer::detectChannel('+258 85 999 8888'));

        $this->assertEquals(Channel::Emola, PhoneNormalizer::detectChannel('861234567'));
        $this->assertEquals(Channel::Emola, PhoneNormalizer::detectChannel('871234567'));

        $this->assertEquals(Channel::Mkesh, PhoneNormalizer::detectChannel('821234567'));
        $this->assertEquals(Channel::Mkesh, PhoneNormalizer::detectChannel('831234567'));

        $this->assertNull(PhoneNormalizer::detectChannel('811234567'));
    }
}
