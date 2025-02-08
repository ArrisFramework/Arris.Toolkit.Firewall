<?php

namespace Arris\Toolkit\FireWall;

enum FireWallState
{
    case ALLOWED;
    case FORBIDDEN;
    case UNDEFINED;

    public static function toBoolean(FireWallState $state):bool
    {
        return match($state) {
            self::ALLOWED       =>  true,
            self::FORBIDDEN     =>  false,
            self::UNDEFINED     =>  throw new \Exception('Undefined arg')
        };
    }
    public static function toEnum(bool $state):FireWallState
    {
        return match($state) {
            true    =>  self::ALLOWED,
            false   =>  self::FORBIDDEN
        };
    }
}
