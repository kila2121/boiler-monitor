<?php

class BoilerCalculator {

    private const FUEL_CALORIFIC_STD = 7000;
    private const FUEL_CALORIFIC_NAT = 8200;
    private const NOMINAL_RADIATION_LOAD = 500;

    public static function calcHeatOutput($steamFlow) {
        $a = -3.13000801729771E-12;
        $b = 3.61373653437203E-09;
        $c = -0.0000016537824248357;
        $d = 0.000241044766937687;
        $e = 0.71492483326346;
        $f = 0.920777873524743;
        
        return $a*pow($steamFlow,5) + $b*pow($steamFlow,4) + $c*pow($steamFlow,3) + $d*pow($steamFlow,2) + $e*$steamFlow + $f;
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

    public static function calculateFull($load, $pressure, $temp, $flueGasTemp, $gasFlow, $excessAir, $coldAirTemp = 20) {
        $results = [];
        
        $results['heat_output'] = self::calcHeatOutput($load);
        $results['flue_gas_loss'] = self::calcFlueGasLoss($flueGasTemp, $coldAirTemp, $excessAir);
        $results['radiation_loss'] = self::calcRadiationLoss($load);
        $results['efficiency'] = self::calcEfficiency($results['flue_gas_loss'], $results['radiation_loss']);
        
        $results['fuel_std'] = self::calcFuelConsumptionStd($results['heat_output'], $results['efficiency']);
        $results['fuel_natural'] = self::calcFuelConsumptionNatural($results['fuel_std']);
        
        return $results;
    }
}
?>