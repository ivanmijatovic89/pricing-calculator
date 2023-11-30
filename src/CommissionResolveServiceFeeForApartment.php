<?php

namespace Bookaweb\PricingCalculator;

use Illuminate\Support\Facades\DB;

class CommissionResolveServiceFeeForApartment
{
    const DEFAULT_SERVICE_FEE_PERCENTAGE = 12;
    const DEFAULT_TAX_FEE_PERCENTAGE = 20;

    public static function get($apartment)
    {
         // APARTMENT
         $commissions = DB::table('commissions')
         ->where(function ($query) use ($apartment) {
             $query->where('commissionable_type', 'App\Ads\Types\Apartment');
             $query->where('commissionable_id', $apartment->id);
         })
         // USER
         ->orWhere(function ($query) use ($apartment) {
             $query->where('commissionable_type', 'App\User');
             $query->where('commissionable_id', $apartment->owner_id);
         })
         // CITY
         ->orWhere(function ($query) use ($apartment) {
             $query->where('commissionable_type', 'App\Location\City');
             $query->where('commissionable_id', $apartment->city_id);
         })
         // COUNTRY
         ->orWhere(function ($query) use ($apartment) {
             $query->where('commissionable_type', 'App\Location\Country');
             $query->where('commissionable_id', $apartment->country_id);
         })
         ->get();

     $check = ['App\Ads\Types\Apartment', 'App\User', 'App\Location\City', 'App\Location\Country'];

     foreach($check as $class) {
         $classCommission = $commissions->where('commissionable_type', $class)->first();
         if($classCommission) {
             return [
                 'sericeFeePercentage' => $classCommission->service_fee,
                 'taxPercentage' => $classCommission->tax,
             ];
         }
     }

     return [
         'sericeFeePercentage' => self::DEFAULT_SERVICE_FEE_PERCENTAGE,
         'taxPercentage' => self::DEFAULT_TAX_FEE_PERCENTAGE,
     ];
    }
}