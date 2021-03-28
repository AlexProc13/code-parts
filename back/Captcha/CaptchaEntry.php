<?php

namespace App\Modules\Captcha;

class CaptchaEntry
{
    /**
     *  We can use service container instead this class
     * @param int $captchaCode
     * @return mixed
     */
    public static function getCaptcha($captchaCode = 0)
    {
        $captchaTypes = config('additional.captcha.types');

        if ($captchaCode == 0) {
            $configCaptcha = config('additional.captcha.defaultType');
            $captchaCode = $configCaptcha;
        }

        return new $captchaTypes[$captchaCode]['class']($captchaCode);
    }
}
