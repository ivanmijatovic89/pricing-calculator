<?php

namespace Bookaweb\PricingCalculator;

use Bookaweb\PricingCalculator\Console\Commands\ComputedPriceCalculatorForAllApartments;
use Bookaweb\PricingCalculator\Console\Commands\ComputedPriceCalculatorForApartment;
use Bookaweb\PricingCalculator\Console\Commands\ComputedPriceCalculatorForUpdatedApartments;
use Illuminate\Support\ServiceProvider;

class PricingCalculatorServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {
        $this->commands([
            ComputedPriceCalculatorForAllApartments::class,
            ComputedPriceCalculatorForApartment::class,
            ComputedPriceCalculatorForUpdatedApartments::class,
        ]);
    }
}
