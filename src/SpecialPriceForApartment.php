<?php

namespace Bookaweb\PricingCalculator;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SpecialPriceForApartment
{
    public static function get($apartment)
    {
        $specialPricePeriods = DB::table('calendar_special_prices')
            ->select(['price', 'start', 'end'])
            ->where('ad_id', $apartment->id)
            ->get();

        $dates = [];
        $dates['price'] = $apartment->detail->price;
        $dates['price_weekend'] = $apartment->detail->price_weekend
            ? $apartment->detail->price_weekend
            : $apartment->detail->price;

        foreach($specialPricePeriods as $specialPrice)
        {
            $period = CarbonPeriod::create($specialPrice->start, Carbon::parse($specialPrice->end)->subDay());

            // Iterate over the period
            foreach ($period as $date) {
                $dates[$date->format('Y-m-d')] = $specialPrice->price;
            }
        }

        return $dates;
    }
}