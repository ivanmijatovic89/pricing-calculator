<?php

namespace Bookaweb\PricingCalculator;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Calendar\CalendarTrait;

class RulePeriodHelper
{
    use CalendarTrait;

    public static function getRulesAppliedOnApartmentForPeriodByDay($apartmentId, $start, $end)
    {
        $periods = self::applyOverlapQueryWithQueryBuilder('calendar_rule_periods', $start, $end, $apartmentId)
            ->get();

        $rules = DB::table('calendar_rules')
            ->whereIn('id', $periods->pluck('calendar_rule_id'))
            ->get();

        foreach ($periods as $period) {
            $rule = $rules->firstWhere('id',$period->calendar_rule_id);;
            $period->rule = $rule
                ? $rule
                : collect();
        }

        return self::parseRulePeriodsByDay($periods);
    }

    public static function parseRulePeriodsByDay($periods){
        $dates = [];

        foreach($periods as $period)
        {
            $dateRangePeriod = CarbonPeriod::create($period->start, \Carbon\Carbon::parse($period->end)->subDay());

            // Iterate over the period
            foreach ($dateRangePeriod as $date) {
                $dates[$date->format('Y-m-d')] = [];

                if($period->rule->min_stay){
                    $dates[$date->format('Y-m-d')]['min_stay'] = $period->rule->min_stay;
                }
                if($period->rule->price_adjustment){
                    $dates[$date->format('Y-m-d')]['price_adjustment'] = $period->rule->price_adjustment;
                }

                $dates[$date->format('Y-m-d')]['rule_id'] = $period->rule->id;
                $dates[$date->format('Y-m-d')]['calendar_rule_id'] = $period->id;
                $dates[$date->format('Y-m-d')]['rule_start'] = $period->start;
                $dates[$date->format('Y-m-d')]['rule_end'] = $period->end;
                $dates[$date->format('Y-m-d')]['color'] = $period->rule->color;
            }
        }
        return $dates;
    }
}