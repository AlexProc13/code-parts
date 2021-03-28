<?php

namespace App\Modules\Translator;

use DB;
use DateTime;
use App\Models\DynamicTranslations;
use App\Modules\Translator\HelperTranslator;

class LoadTranslator
{
    public static function clear()
    {
        DynamicTranslations::truncate();
    }

    public static function set($values, $group, $mode = null)
    {
        $languages = config('translations.available_locales');
        $defaultLanguage = config('translations.defaultLang');
        $keyGroups = config('translations.dynamicGroupKeys');

        $setTranslations = [];
        $setIndexTranslations = [];

        if ($mode == null) {
            $languages = [$defaultLanguage];
        }

        foreach ($languages as $language) {
            foreach ($values as $key => $value) {
                $issetTranslate = DynamicTranslations::where('item', HelperTranslator::genKey($group, $value))
                    ->where('locale', $language)
                    ->first();

                $genKey = HelperTranslator::genKey($group, $value);
                if (!$issetTranslate) {
                    $setTranslations[] = [
                        'locale' => $language,
                        'group' => $keyGroups[$group],
                        'item' =>  $genKey,
                        'text' => trim($value),
                        'created_at' => new DateTime(),
                        'updated_at' => new DateTime(),
                    ];
                }

                if ($language == $defaultLanguage) {
                    //make index
                    $setIndexTranslations[] = [
                        'item' => $genKey,
                        'value' => $genKey,
                    ];
                }
            }
        }

        //set
        DynamicTranslations::insert($setTranslations);
        DB::table('dynamic_index')->insert($setIndexTranslations);
    }
}
