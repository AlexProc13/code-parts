<?php

namespace App\Modules\Translator;

class HelperTranslator
{
    public static function genKey($group, $value)
    {
        return crc32($group . config('translations.sign') . mb_strtolower($value));
    }
}
