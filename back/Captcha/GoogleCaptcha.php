<?php

namespace App\Modules\Captcha;

use Exception;
use GuzzleHttp\Client;

class GoogleCaptcha extends Captcha
{
    public function getHtmlOrigin()
    {
        $captchaCode = $this->captchaCode;
        $captchaTypes = config('additional.captcha.types');
        $key = $captchaTypes[$captchaCode]['data']['key'];

        //template_captcha
        return view('api.captcha.google_captcha')
            ->with([
                'key' => $key,
                'captchaCode' => $captchaCode,
            ])->render();
    }

    public function checkResult($result)
    {
        $captchaCode = $this->captchaCode;
        $captchaTypes = config('additional.captcha.types');
        $captchaData = $captchaTypes[$captchaCode]['data'];

        try {
            $client = new Client([
                'timeout' => $captchaData['timeout'],
            ]);

            $response = $client->request($captchaData['verifyMethod'], $captchaData['verifyUrl'], [
                'query' => [
                    'secret' => $captchaData['secret_key'],
                    'response' => $result,
                ],
            ]);

            $jsonData = json_decode($response->getBody()->getContents());

            if ($jsonData->success === false) {
                throw new Exception('google_captcha');
            }
        } catch (Exception  $ex) {
            return [
                'status' => false,
            ];
        }

        return [
            'status' => true,
        ];
    }
}
