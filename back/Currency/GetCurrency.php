<?php

namespace App\Modules\Currency;

use App\Models\Currency;
use App\Modules\RawLog\RawLog;
use Exception;
use GuzzleHttp\Client;

class GetCurrency
{
    public static function handler()
    {
        $rawLog = app()->make(RawLog::class);
        $rawLogTypes = config('additional.rawLog');
        $rawLog->startLog($rawLogTypes['getCurrency'], 0, [
            'user' => get_current_user(),
        ]);

        $getCurrencyData = config('additional.currencyData');

        $currencyProvider = [
            'currencyPath' => $getCurrencyData['data'],
            'access_key' => $getCurrencyData['key'],
            'path' => $getCurrencyData['path'],
            'source' => config('app.currency'),
        ];

        try {
            $guzzle = new Client();
            $rawRequest = $guzzle->request('GET', $currencyProvider['path'], [
                'headers' => [],
                'query' => [
                    'app_id' => $currencyProvider['access_key'],
                    'base' => $currencyProvider['source'],
                ],
            ])->getBody()->getContents();
            $valueData = json_decode($rawRequest, true);

            $currencies = json_decode(file_get_contents($currencyProvider['currencyPath']), true);

            foreach ($valueData['rates'] as $key => $item) {
                Currency::updateOrCreate(['code' => $key], [
                    'active' => 1,
                    'name' => isset($currencies[$key]) ? $currencies[$key]['name'] : $key,
                    'symbol' => isset($currencies[$key]) ? $currencies[$key]['symbol'] : $key,
                    'exact_value' => $item,
                    'value' => (int)$item,
                ]);
            }
        } catch (Exception $ex) {
            $rawLog->endLog(['error' => $ex->getMessage()], []);
            throw new Exception('get currency');
        }

        $rawLog->endLog(['status' => true], []);
    }
}
