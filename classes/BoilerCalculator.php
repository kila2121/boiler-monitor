<?php

class BoilerCalculator {

    private const FUEL_CALORIFIC_STD = 7000;
    private const FUEL_CALORIFIC_NAT = 8200;
    private const NOMINAL_RADIATION_LOAD = 500;

    public static function calcFeedWaterTemp($load) {
        $a = 7.79322839345332E-09;
        $b = -0.0000105452598520494;
        $c = 0.00510424514002294;
        $d = -0.892865352829383;
        $e = 233.462608934323;
        
        return $a*pow($load,4) + $b*pow($load,3) + $c*pow($load,2) + $d*$load + $e;
    }

    public static function calcReheatTemp($load) {
        $a = 4.56706339582822E-11;
        $b = -6.84234007287908E-08;
        $c = 0.0000358888842479737;
        $d = -0.00746931693086592;
        $e = 0.503600110660366;
        $f = 523.632291364987;
        
        return $a*pow($load,5) + $b*pow($load,4) + $c*pow($load,3) + $d*pow($load,2) + $e*$load + $f;
    }

    public static function calcO2Content($load) {
        $a = 2.52818170929949E-10;
        $b = -3.08913483291984E-07;
        $c = 0.000143144002654279;
        $d = -0.0343874495543735;
        $e = 5.17041932581538;
        
        return $a*pow($load,4) + $b*pow($load,3) + $c*pow($load,2) + $d*$load + $e;
    }
    
    public static function calcFlueGasTemp($load) {
        $a = 4.54585421632821E-11;
        $b = -7.58667487146456E-08;
        $c = 0.0000497558548521469;
        $d = -0.0160125958583649;
        $e = 2.54250829933757;
        $f = -30.6875551293643;
        
        return $a*pow($load,5) + $b*pow($load,4) + $c*pow($load,3) + $d*pow($load,2) + $e*$load + $f;
    }
    
    public static function calcHeatOutput($steamFlow) {
        $a = -3.13000801729771E-12;
        $b = 3.61373653437203E-09;
        $c = -0.0000016537824248357;
        $d = 0.000241044766937687;
        $e = 0.71492483326346;
        $f = 0.920777873524743;
        
        return $a*pow($steamFlow,5) + $b*pow($steamFlow,4) + $c*pow($steamFlow,3) + $d*pow($steamFlow,2) + $e*$steamFlow + $f;
    }
    
    public static function calcEnthalpy($temp, $pressure) {
        $T = $temp + 273.15;
        $P = $pressure * 0.0980665;
        $Tr = $T / 1000;
        
        $h0 = 2.12787e3 + 1.48285e3 * $Tr + 3.79026e2 * pow($Tr, 2) + 4.6174 * 10 * log($Tr);
        
        $A = 3.237e-4 + 3 * (-1.1354e-3) / pow($Tr, 2) + 3 * (-4.381e-4) / pow($Tr - 0.21, 2) 
            + 2 * (-4.381e-4) * 0.21 / pow($Tr - 0.21, 3);
        
        $B = 5.6084e-6 + 9 * (-2.5993e-6) / pow($Tr, 8) + 15 * (-1.2604e-8) / pow($Tr, 14);
        
        $h = ($h0 + ($A * $P + $B * pow($P, 2) / 2) * 1000) / 4.1868;
        
        return $h;
    }

    public static function calcFlueGasLoss($flueGasTemp, $coldAirTemp, $excessAir) {
        $excessAirSection = $excessAir - 0.25;
        if ($excessAirSection <= 0) $excessAirSection = 1.0;
        
        $loss = (3.53 * $excessAirSection + 0.6) 
                * ($flueGasTemp - $excessAirSection / ($excessAirSection + 0.18) * $coldAirTemp) 
                * (0.9805 + 0.00013 * $flueGasTemp) / 100;
        
        return max(0, $loss);
    }
    
    public static function calcRadiationLoss($load) {
        if ($load <= 0) return 0.35;
        return 0.35 * self::NOMINAL_RADIATION_LOAD / $load;
    }
    
    public static function calcEfficiency($flueGasLoss, $radiationLoss) {
        $efficiency = 100 - $flueGasLoss - $radiationLoss;
        return max(0, min(100, $efficiency));
    }
    
    public static function calcFuelConsumptionStd($heatOutput, $efficiency) {
        if ($efficiency <= 0) return 0;
        return $heatOutput / self::FUEL_CALORIFIC_STD / ($efficiency / 100) * 1000;
    }
    
    public static function calcFuelConsumptionNatural($fuelStd, $calorificValue = null) {
        $cv = $calorificValue ?? self::FUEL_CALORIFIC_NAT;
        return $fuelStd * self::FUEL_CALORIFIC_STD / $cv;
    }
    
    public static function calcExcessAir($o2Content) {
        $o2 = max(0, min(21, $o2Content));
        if (21 - $o2 <= 0.01) return 999;
        return (21 - 0.1 * $o2) / (21 - $o2);
    }
    
    public static function calcFuelImpact($param, $deviation, $steamFlow) {
        $coefficients = [
            'pressure' => 0.05,
            'temp' => 0.028,
            'reheat_temp' => 0.016,
            'feedwater_temp' => 0.044,
            'o2' => 0.15,
        ];
        
        $coef = $coefficients[$param] ?? 0.05;
        $sign = $deviation > 0 ? -1 : 1;
        
        return $sign * $coef * abs($deviation) * 350 * ($steamFlow / 3.2) / 100000;
    }
    
    public static function calculateEfficiencyScore($actualEfficiency, $targetEfficiency, $deviations) {
        $score = 100;
        
        if ($targetEfficiency > 0) {
            $efficiencyPenalty = ($targetEfficiency - $actualEfficiency) / $targetEfficiency * 50;
            $score -= max(0, min(50, $efficiencyPenalty));
        }
        
        $deviationWeights = [
            'steam_temperature' => 0.3,
            'o2_content' => 0.25,
            'flue_gas_temp' => 0.25,
            'steam_pressure' => 0.1,
            'feedwater_temp' => 0.1
        ];
        
        $totalDeviationPenalty = 0;
        foreach ($deviations as $param => $data) {
            if (isset($deviationWeights[$param]) && ($data['status'] ?? '') === '⚠️') {
                $penalty = $deviationWeights[$param] * min(20, abs($data['dev'] ?? 0) / 2);
                $totalDeviationPenalty += $penalty;
            }
        }
        
        $score -= min(40, $totalDeviationPenalty);
        $score = max(0, min(100, $score));
        
        return [
            'score' => round($score, 1),
            'grade' => self::getGrade($score),
            'recommendations' => self::getRecommendations($deviations)
        ];
    }

    private static function getGrade($score) {
        if ($score >= 90) return 'Отлично';
        if ($score >= 75) return 'Хорошо';
        if ($score >= 60) return 'Удовлетворительно';
        if ($score >= 40) return 'Требует внимания';
        return 'Критично';
    }

    private static function getRecommendations($deviations) {
        $recs = [];
        foreach ($deviations as $param => $data) {
            if (($data['status'] ?? '') !== '⚠️') continue;
            
            $dev = $data['dev'] ?? 0;
            $absDev = abs($dev);
            
            $rec = match($param) {
                'o2_content' => '⚠️ Содержание O₂ ' . ($dev > 0 ? 'выше' : 'ниже') . 
                                ' нормы на ' . $absDev . '%. Настройте подачу воздуха.',
                'flue_gas_temp' => '⚠️ Температура уходящих газов ' . ($dev > 0 ? 'выше' : 'ниже') . 
                                   ' нормы на ' . $absDev . '°C. Очистите поверхности нагрева.',
                'steam_temperature' => '⚠️ Температура пара ' . ($dev > 0 ? 'выше' : 'ниже') . 
                                      ' нормы на ' . $absDev . '°C. Проверьте работу горелок.',
                'steam_pressure' => '⚠️ Давление пара ' . ($dev > 0 ? 'выше' : 'ниже') . 
                                   ' нормы на ' . $absDev . ' кгс/см². Проверьте настройки регуляторов.',
                'feedwater_temp' => '⚠️ Температура питательной воды отклоняется на ' . $absDev . 
                                    '°C. Проверьте работу подогревателей.',
                default => "⚠️ Параметр $param: требуется проверка."
            };
            $recs[] = $rec;
        }
        
        if (empty($recs)) {
            $recs[] = '✅ Все параметры в норме. Режим работы оптимален.';
        }
        
        return $recs;
    }
    
    public static function calculateOptimalLoad($currentLoad, $minLoad, $maxLoad, $efficiency) {
        $optimalRange = [
            'min' => $maxLoad * 0.6,
            'max' => $maxLoad * 0.8
        ];
        
        $status = 'normal';
        $message = '';
        $recommendedLoad = $currentLoad;
        
        if ($currentLoad < $optimalRange['min']) {
            $status = 'warning';
            $message = 'Нагрузка ниже оптимальной. КПД может быть снижен.';
            $recommendedLoad = $optimalRange['min'];
        } elseif ($currentLoad > $optimalRange['max']) {
            $status = 'warning';
            $message = 'Нагрузка выше оптимальной. Возможен перегрев и снижение ресурса.';
            $recommendedLoad = $optimalRange['max'];
        } else {
            $status = 'optimal';
            $message = 'Нагрузка в оптимальном диапазоне.';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'current_load' => round($currentLoad, 1),
            'optimal_min' => round($optimalRange['min'], 1),
            'optimal_max' => round($optimalRange['max'], 1),
            'recommended_load' => round($recommendedLoad, 1)
        ];
    }

    public static function calculateFull($load, $pressure, $temp, $flueGasTemp, $gasFlow, $o2Content = null, $coldAirTemp = 20) {
        $results = [];
        
        $results['feedwater_temp_ref'] = self::calcFeedWaterTemp($load);
        $results['reheat_temp_ref'] = self::calcReheatTemp($load);
        $results['o2_ref'] = self::calcO2Content($load);
        $results['flue_gas_temp_ref'] = self::calcFlueGasTemp($load);
        $results['heat_output'] = self::calcHeatOutput($load);
        
        $o2Actual = $o2Content ?? $results['o2_ref'];
        $results['excess_air'] = self::calcExcessAir($o2Actual);
        
        $results['flue_gas_loss'] = self::calcFlueGasLoss($flueGasTemp, $coldAirTemp, $results['excess_air']);
        $results['radiation_loss'] = self::calcRadiationLoss($load);
        $results['efficiency'] = self::calcEfficiency($results['flue_gas_loss'], $results['radiation_loss']);
        
        $results['fuel_std'] = self::calcFuelConsumptionStd($results['heat_output'], $results['efficiency']);
        $results['fuel_natural'] = self::calcFuelConsumptionNatural($results['fuel_std']);
        
        return $results;
    }
}
?>