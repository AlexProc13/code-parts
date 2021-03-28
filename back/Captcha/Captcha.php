<?php

namespace App\Modules\Captcha;

use URL;

abstract class Captcha
{
    protected $captchaCode;

    public function __construct($captchaCode)
    {
        $this->captchaCode = $captchaCode;
    }

    public function getHtml()
    {
        //to config link
        $frameUrl = URL::temporarySignedRoute('captcha_data', now()->addMinutes(10));

        return view('api.captcha.common.template_captcha')
            ->with([
                'frameUrl' => $frameUrl,
            ])->render();
    }

    abstract protected function getHtmlOrigin();

    abstract protected function checkResult($result);
}
