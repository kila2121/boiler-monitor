<?php

class BoilerCalculator {

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
        
        $enthalpy = (2.12787*pow(10,3) + 
                    1.48285*pow(10,3)*$Tr + 
                    3.79026*pow(10,2)*pow($Tr,2) + 
                    4.6174*10*log($Tr) + 
                    (
                        (3.237*pow(10,-4) + 
                         3*(-1.1354*pow(10,-3))/pow($Tr,2) + 
                         3*(-4.381*pow(10,-4))/pow(($Tr-0.21),2) + 
                         2*(-4.381*pow(10,-4))*0.21/pow(($Tr-0.21),3)
                        ) * $P + 
                        (5.6084*pow(10,-6) + 
                         9*(-2.5993*pow(10,-6))/pow($Tr,8) + 
                         15*(-1.2604*pow(10,-8))/pow($Tr,14)
                        ) * pow($P,2) / 2
                    ) * 1000
                ) / 4.1868;
        
        return $enthalpy;
    }

    public static function calcFlueGasLoss($flueGasTemp, $coldAirTemp, $o2Content, $excessAir) {
        $loss = (3.53*$excessAir + 0.6) * ($flueGasTemp - $excessAir/($excessAir+0.18)*$coldAirTemp) * (0.9805 + 0.00013*$flueGasTemp) / 100;
        return $loss;
    }
    

    public static function calcRadiationLoss($load) {
        $nominalLoad = 500;
        return 0.35 * $nominalLoad / $load;
    }
    

    public static function calcEfficiency($flueGasLoss, $radiationLoss) {
        return 100 - $flueGasLoss - $radiationLoss;
    }
    

    public static function calcFuelConsumptionStd($heatOutput, $efficiency) {
        return $heatOutput / 7000 / ($efficiency/100) * 1000;
    }
    

    public static function calcFuelConsumptionNatural($fuelStd, $calorificValue = 8200) {
        return $fuelStd * 7000 / $calorificValue;
    }

    public static function calcExcessAir($o2Content) {
        return (21 - 0.1*$o2Content) / (21 - $o2Content);
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

    public static function calculateFull($load, $pressure, $temp, $flueGasTemp, $gasFlow, $o2Content = null, $coldAirTemp = 20) {
        $results = [];
        
        $results['feedwater_temp_ref'] = self::calcFeedWaterTemp($load);
        $results['reheat_temp_ref'] = self::calcReheatTemp($load);
        $results['o2_ref'] = self::calcO2Content($load);
        $results['flue_gas_temp_ref'] = self::calcFlueGasTemp($load);
        $results['heat_output'] = self::calcHeatOutput($load);
        
        $o2Actual = $o2Content ?? $results['o2_ref'];
        $results['excess_air'] = self::calcExcessAir($o2Actual);
        
        $results['flue_gas_loss'] = self::calcFlueGasLoss($flueGasTemp, $coldAirTemp, $o2Actual, $results['excess_air']);
        $results['radiation_loss'] = self::calcRadiationLoss($load);
        $results['efficiency'] = self::calcEfficiency($results['flue_gas_loss'], $results['radiation_loss']);
        
        $results['fuel_std'] = self::calcFuelConsumptionStd($results['heat_output'], $results['efficiency']);
        $results['fuel_natural'] = self::calcFuelConsumptionNatural($results['fuel_std']);
        
        return $results;
    }
}
?>