<?php

namespace Ddd\Infrastructure\Persistence\Doctrine\Type;

use Ddd\Domain\Model\Currency;
use Ddd\Domain\Model\Money;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class CustomSerializationMoneyType extends TextType
{
    const CUSTOM_SERIALIZATION_MONEY = 'custom_serialization_money';

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $value = explode('|', parent::convertToPHPValue($value, $platform));
        return new Money(
            $value[0],
            new Currency($value[1])
        );
    }

    /**
     * @param Money $value
     * @param AbstractPlatform $platform
     * @return string
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return implode('|', [$value->amount(), $value->currency()->isoCode()]);
    }

    public function getName()
    {
        return self::CUSTOM_SERIALIZATION_MONEY;
    }
}
