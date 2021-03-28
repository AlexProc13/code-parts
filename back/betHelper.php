<?php

namespace App\Modules\Bets;

class BetHelper
{
    public static function numberOfCombinations($start, $end)
    {
        return factorial($end) / (factorial($start) * factorial($end - $start));
    }

    public static function combineWithoutRepetitions($comboOptions, $comboLength)
    {
        $combos = [];
        if ($comboLength === 1) {
            $combos = array_map(function ($comboOption) {
                return [$comboOption];
            }, $comboOptions);
        } else {
            foreach ($comboOptions as $optionIndex => $comboOption) {
                $sliceArray = array_slice($comboOptions, $optionIndex + 1);
                $smallerCombos = self::combineWithoutRepetitions($sliceArray, $comboLength - 1);

                foreach ($smallerCombos as $smallerCombo) {
                    array_push($combos, array_merge([$comboOption], $smallerCombo));
                }
            }
        }

        return $combos;
    }

    public static function systemCount($bets, $stake, $keyParts, $options)
    {
        $amount = 0;
        $keyData = $keyParts;
        $start = (int)$keyParts[0];
        $end = (int)$keyParts[1];

        $mode = isset($keyData[2]) ? (int)$keyData[2] : null;
        $min = $options['min'] - 1;
        $countBets = count($bets);
        $keyBet = $options['key'];

        if ($start < $end) {
            $countCombinations = self::numberOfCombinations($start, $end);
            if ($countBets > 0) {
                $arrayOdds = self::systemGenerateBetArray($bets, $start, $stake, $keyBet);
                foreach ($arrayOdds as $itemArrayOdds) {
                    $amount = $amount + $itemArrayOdds;
                }
            }
        } else {
            $countCombinations = 0;
            for ($i = $min; $i < $end; $i++) {
                $countCombinations += self::numberOfCombinations($i, $end);

                if ($countBets > 0) {
                    $arrayOdds = self::systemGenerateBetArray($bets, $i, $stake, $keyBet);
                    foreach ($arrayOdds as $itemArrayOdds) {
                        $amount = $amount + $itemArrayOdds;
                    }
                }
            }

            //full express
            $countCombinations += 1;

            if ($countBets > 0) {
                $fullExpressOdds = 1;
                foreach ($bets as $bet) {
                    $fullExpressOdds = $bet[$keyBet] * $fullExpressOdds;
                }
                $amount = $amount + ($fullExpressOdds * $stake);
            }

            //and single
            if ($mode == 'full') {
                $countCombinations += $end;

                if ($countBets > 0) {
                    foreach ($bets as $bet) {
                        $amount = $amount + ($bet[$keyBet] * $stake);
                    }
                }
            }
        }

        return [
            'winAmount' => $amount,
            'countCombinations' => $countCombinations,
            'needAmount' => $countCombinations * $stake,
        ];
    }


    public static function systemGenerateBetArray($comboOptions, $comboLength, $stake, $key)
    {
        $combos = [];
        if ($comboLength === 1) {
            $combos = array_map(function ($comboOption) {
                return [$comboOption];
            }, $comboOptions);
        } else {
            foreach ($comboOptions as $optionIndex => $currentOption) {
                $sliceArray = array_slice($comboOptions, $optionIndex + 1);
                $smallerCombos = self::combineWithoutRepetitions($sliceArray, $comboLength - 1);
                foreach ($smallerCombos as $smallerCombo) {
                    $value = 1;
                    foreach ($smallerCombo as $smallerComboItem) {
                        $value = $value * $smallerComboItem[$key];
                    }

                    $generalOdds = $value * $currentOption[$key];
                    array_push($combos, $generalOdds * $stake);
                }
            }
        }

        return $combos;
    }
}