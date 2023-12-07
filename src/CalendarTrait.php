<?php

namespace Bookaweb\PricingCalculator;

trait CalendarTrait
{
    // check if there is overlap with other manual events.
    // check if day before or day after we have "manual" events ( no need for extra functionality, code below already do it)
    public static function getOverlap($start, $end, $adId){
        return self::applyOverlapQuery($start, $end, $adId)->get();
    }

    public static function applyOverlapQuery($start, $end, $adId){
        return self::where( function($query) use ($start, $end){
            $query->where(function($query) use ($start, $end){
                $query->whereBetween('start', [$start, $end]);
                $query->orWhereBetween('end', [$start, $end]);
            });
            $query->orWhere(function($query) use ($start, $end){
                $query->whereRaw('? between start and end', [$start]);
                $query->orWhereRaw('? between start and end', [$end]);
            });
        })
        ->where('ad_id', $adId);
    }

    public static function applyOverlapQueryWithQueryBuilder($tableName, $start, $end, $adId)
    {
        return \DB::table($tableName)
            ->where( function($query) use ($start, $end){
                $query->where(function($query) use ($start, $end){
                    $query->whereBetween('start', [$start, $end]);
                    $query->orWhereBetween('end', [$start, $end]);
                });
                $query->orWhere(function($query) use ($start, $end){
                    $query->whereRaw('? between start and end', [$start]);
                    $query->orWhereRaw('? between start and end', [$end]);
                });
            })
            ->where('ad_id', $adId);
    }
}
