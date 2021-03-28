<?php

namespace App\Modules\Translator;

use DB;
use App;
use Cache;
use App\Models\DynamicTranslations;
use App\Modules\Translator\HelperTranslator;

class Translator
{
    public static function getTranslations(array $keys = [])
    {
        $lang = App::getLocale();
        $defaultLang = config('translations.defaultLang');

        $param['columnsAlias'] = [
            'dynamic_translations.group as group',
            'dynamic_translations.item as item',
            'dynamic_translations.text as text',
            'cur_lang.group as cur_group',
            'cur_lang.item as cur_item',
            'cur_lang.text as cur_text',
        ];

        $param['defaultLang'] = $defaultLang;
        $param['currentLang'] = $lang;
        $whereCompare = [
            ['dynamic_translations.locale', '=', $param['defaultLang']],
        ];

        /* ACT */
        $values = [];
        $newKeys = [];
        foreach ($keys as $key) {
            $keyParts = explode(config('translations.sign'), $key);
            $currentKey = HelperTranslator::genKey($keyParts[0], $keyParts[1]);
            $newKeys[] = $currentKey;
            $values[$currentKey] = $key;
        }

        $result = [];
        //to do get from cache
        foreach ($newKeys as $keyArray => $key) {
            $value = Cache::get(self::keyCache($lang, $key));

            if ($value) {
                $result[$values[$key]] = $value;
                unset($newKeys[$keyArray]);
            }
        }

        if (!empty($newKeys)) {
            $items = DynamicTranslations::leftJoin(
                'dynamic_translations as cur_lang',
                function ($join) use ($param) {
                    $join->on('dynamic_translations.item', '=', 'cur_lang.item')
                        ->on('dynamic_translations.group', '=', 'cur_lang.group')
                        ->where('cur_lang.locale', '=', $param['currentLang']);
                }
            )
                ->whereIn('dynamic_translations.item', $newKeys)
                ->where($whereCompare)
                ->select($param['columnsAlias'])
                ->get()
                ->keyBy('item')
                ->toArray();
        }

        foreach ($newKeys as $key) {
            $finalKey = $values[$key];
            $parts = explode(config('translations.sign'), $finalKey);
            $result[$finalKey] = $parts[1];

            if (isset($items[$key])) {
                if (!is_null($items[$key]['text'])) {
                    $result[$finalKey] = $items[$key]['text'];
                }

                if (!is_null($items[$key]['cur_text'])) {
                    $result[$finalKey] = $items[$key]['cur_text'];
                }
            }
            //set to cache
            Cache::put(self::keyCache($lang, $key), $result[$finalKey], config('translations.cache.ttl'));
        }

        return $result;
    }

    protected static function keyCache($lang, $key)
    {
        return config('translations.cache.suffix') . $lang . $key;
    }
}
