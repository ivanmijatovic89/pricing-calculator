<?php

namespace Bookaweb\PricingCalculator\Controllers;

use App\Http\Controllers\Controller;
use App\Ads\Types\Apartment;
use App\Calendar\Controllers\CalendarController;
use Carbon\CarbonPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Bookaweb\PricingCalculator\Requests\CalculatePriceRequest;
use App\Calendar\RulePeriod;
use App\Commissions\Commission;
use App\Promocode\Promocode;
use Bookaweb\PricingCalculator\SpecialPriceForApartment;
use Illuminate\Http\Request;

class PriceCalculatorController extends Controller
{
    public $start;
    public $end;
    public $guests;
    public $parking;
    public $period;
    public $dates;
    public $pricing;
    public $weeklyDiscount = false;
    public $monthlyDiscount = false;
    public $discount = 0;
    public $promocode_discount;
    public $promocode_discount_percentage;
    public $extraGuestFee = 0;

    public $total = 0;
    public $total_before_promocode = 0;
    public $baseTotal = 0;
    public $baseAvg = 0;

    public $ownerTotal = 0;
    // Parking
    public $parkingTotal = 0;
    // Service Fee
    public $serviceFee = 0;
    public $taxOnServiceFee = 0;
    public $serviceFeeTotal = 0;
    public $sericeFeePercentage;
    public $taxPercentage;

    public $specialPricing;
    public $rulesByDate;

    const MONTH_LENGTH = 29;

    public function singleDayPrice($date)
    {
        $price = $this->pricing->price;
        $isWeekend = false;
        // This nightly price will replace your base price for every Friday and Saturday.
        if ($date->dayOfWeek === 5 || $date->dayOfWeek === 6) {
            $isWeekend = true;
            if($this->pricing->price_weekend){
                $price = $this->pricing->price_weekend;
            }
        }
        $dateFormated = $date->clone()->format('Y-m-d');

        // set special price if exists
        if(isset($this->specialPricing[$dateFormated])){
            $price = $this->specialPricing[$dateFormated];
        }

        // Apply rule price adjustment if it has it
        $price = $this->applyPriceAdjustmentIfHasRuleOnDate($dateFormated, $price);


        // EXTRA GUEST
        $extraGuestFee = 0;
        $extraGuestFeeAfterPriceAdjustment = 0;
        if ($this->pricing->charge_after_guest > 0 && $this->guests > $this->pricing->charge_after_guest){
            $extraGuestFee = ($this->guests - $this->pricing->charge_after_guest) * $this->pricing->price_per_guest;
            $extraGuestFeeAfterPriceAdjustment = $this->applyPriceAdjustmentIfHasRuleOnDate($dateFormated, $extraGuestFee);
        }

        // PARKING
        $parking = 0;
        $parkingAfterPriceAdjustment = 0;
        if ($this->parking
            && $this->pricing->parking['available'] === 'paid'
            && $this->pricing->parking['price_type'] === 'per_day'
            && $this->pricing->parking['price']) {
                $parking = $this->pricing->parking['price'];
                $parkingAfterPriceAdjustment = $this->applyPriceAdjustmentIfHasRuleOnDate($dateFormated, $this->pricing->parking['price']);
        }

        return [
            'dayOfWeek'=> $date->format('l'),
            // 'dayOfWeekIndex'=> $date->dayOfWeek,
            'specialPrice' => isset($this->specialPricing[$dateFormated])
                ? $this->specialPricing[$dateFormated]
                : null,
            'price' => $price,
            'isWeekend' => $isWeekend,
            'extraGuestFee' => $extraGuestFee,
            'extraGuestFeeAfterPriceAdjustment' => $extraGuestFeeAfterPriceAdjustment,
            'parking' => $parking,
            'parkingAfterPriceAdjustment' => $parkingAfterPriceAdjustment,
            'priceAdjustment' => $this->getPriceAdjustmentForDate($dateFormated),
        ];
    }
    public function getPriceAdjustmentForDate($date){
        if(isset($this->rulesByDate[$date]) && isset($this->rulesByDate[$date]['price_adjustment'])){

            return $this->rulesByDate[$date]['price_adjustment'];
        }
        return null;
    }
    public function applyPriceAdjustmentIfHasRuleOnDate($date, $price)
    {
        $priceAdjustment = $this->getPriceAdjustmentForDate($date);

        if($priceAdjustment){
            // apply price adjustment
            if ($priceAdjustment < 0 || $priceAdjustment > 0) { // decrease
                $price = round(
                    ( $price * ((100 + $priceAdjustment)/100) )
                    , 2
                );
            }
        }

        return $price;
    }

    public function overwriteParamsFromRequest($request)
    {
        if($request->has('overwrite'))
        {
            $overwrite = json_decode($request->input('overwrite'), true);

            $allowed = [ 'price', 'price_weekend', 'charge_after_guest',
                'price_per_guest', 'weekly_discount', 'monthly_discount', 'guests'
            ];

            foreach($allowed as $prop){
                // Prop types are not same > Example: from db we get string from request we get int
                if(isset($overwrite[$prop]) && $overwrite[$prop] !== null && $overwrite[$prop] !== ''){
                    $this->pricing->{$prop} = $overwrite[$prop];
                }
            }
        }
    }

    public function computedCalculatePrice(Apartment $apartment, CalculatePriceRequest $request)
    {
        $this->init($apartment, $request);

        $data = [];
        for ($guests=1; $guests <= $apartment->detail->guests ; $guests++)
        {
            $this->guests = $guests;
            $data[$this->guests]['price'] = $this->calculate($apartment, $request);
        }

        $this->weeklyDiscount = false;
        $this->monthlyDiscount = true;
        for ($guests=1; $guests <= $apartment->detail->guests ; $guests++)
        {
            $this->guests = $guests;
            $data[$this->guests]['monthly_discount'] = $this->calculate($apartment, $request);
        }

        $this->weeklyDiscount = true;
        $this->monthlyDiscount = false;
        for ($guests=1; $guests <= $apartment->detail->guests ; $guests++)
        {
            $this->guests = $guests;
            $data[$this->guests]['weekly_discount'] = $this->calculate($apartment, $request);
        }

        return $data;
    }


    public function init(Apartment $apartment, CalculatePriceRequest $request)
    {
        // PREPARE START
        $this->start = Carbon::parse($request->start);
        $this->end =  Carbon::parse($request->end);
        $this->parking =  $request->parking;
        $this->guests = $request->guests;
        $this->period = CarbonPeriod::create($this->start, $this->end->clone()->subDay());
        $this->pricing = $apartment->detail;
        $this->overwriteParamsFromRequest($request);

        $this->specialPricing = SpecialPriceForApartment::get($apartment);
        $this->rulesByDate = RulePeriod::getRulesAppliedOnApartmentForPeriodByDay($apartment->id, $request->start, $request->end);
        $this->dates =  new Collection();
        // PREPARE END
    }


    public function calculatePrice(Apartment $apartment, CalculatePriceRequest $request)
    {
        $this->init($apartment, $request);

        return $this->calculate($apartment, $request);
    }

    public function calculate($apartment, $request){

        // Iterate over the period
        foreach ($this->period as $date) {
            $this->dates[$date->format('Y-m-d')] = $this->singleDayPrice($date);
        }

        // BASE TOTAL + AVG
        $this->baseTotal = $this->dates->sum('price');
        $this->baseAvg = $this->dates->avg('price');

        // EXTRA GUEST FEE
        $this->extraGuestFee = $this->dates->sum('extraGuestFeeAfterPriceAdjustment');

        // PARKING
        $this->parkingTotal = 0;
        if ($this->parking && $this->pricing->parking['available'] === 'paid') {
            // PARKING PER DAY
            if ($this->pricing->parking['price_type'] === 'per_day') {
                $this->parkingTotal = $this->dates->sum('parkingAfterPriceAdjustment');
            }
            // PARKING PER STAY
            else if ($this->pricing->parking['price_type'] === 'per_stay') {
                $this->parkingTotal = $this->pricing->parking['price'];
            }
        }

        $taxableWithoutDiscount = $this->baseTotal + $this->extraGuestFee + $this->parkingTotal;

        // $data = [
        //     'monthlyDiscount' => $this->monthlyDiscount,
        //     'weeklyDiscount' => $this->weeklyDiscount,
        //     'guests' => $this->guests,
        //     'start' => $this->start->clone()->format('Y-m-d'),
        //     'end' => $this->end->clone()->format('Y-m-d'),
        // ];
        // \Log::info($data);

        // MONTHLY DISCOUNT AFTER 29 DAYS
        if (count($this->period) >= self::MONTH_LENGTH || $this->monthlyDiscount === true) {
            $this->monthlyDiscount = true;
            $this->discount = ($taxableWithoutDiscount * ($this->pricing->monthly_discount / 100));
        }
        // WEEKLY DISCOUNT
        else if (count($this->period) >= 7 || $this->weeklyDiscount === true) {
            $this->weeklyDiscount = true;
            $this->discount = ($taxableWithoutDiscount * ($this->pricing->weekly_discount / 100));
        }

        $taxable = $taxableWithoutDiscount - $this->discount;

        $commission = Commission::resolveServiceFeeForApartment($apartment);
        // $commission = \Illuminate\Support\Facades\Cache::remember('commission_for_apartment_' . $apartment->id, 5 * 60, function () use ($apartment) {
        //     return Commission::resolveServiceFeeForApartment($apartment);
        // });
        $this->sericeFeePercentage = $commission['sericeFeePercentage'];
        $this->taxPercentage = $commission['taxPercentage'];

        // OLD WAY...
        // TO add 16.7% + PDV on $taxable
        // znaci da uveća taxable za 16.7%;
        // // SERVICE FEE ( without tax)
        // $this->serviceFee = $taxable * ($this->sericeFeePercentage/100);

        // // TAXES
        // $this->taxOnServiceFee = $this->serviceFee * ($this->taxPercentage/100);

        // // SERVICE FEE TOTAL = SERVICE FEE (without tax) + TAXES
        // $this->serviceFeeTotal = $this->serviceFee + $this->taxOnServiceFee;

        // $this->total = $taxable + $this->serviceFeeTotal;

        // // ToDo change to  $this->ownerTotal = $taxable
        // $this->ownerTotal = $this->total - $this->serviceFeeTotal;


        // NEW WAY
        // FEE = 16.7 of total amount

        $this->ownerTotal = $taxable;

        $this->total = self::calculateTotalBasedOnBasePriceAndFee($taxable, $this->sericeFeePercentage, $this->taxPercentage);
        $this->total_before_promocode = $this->total;

        $this->serviceFee = $this->total * ($this->sericeFeePercentage/100);

        $this->taxOnServiceFee = $this->total - $taxable - $this->serviceFee;

        $this->serviceFeeTotal = $this->serviceFee + $this->taxOnServiceFee;

        if($request->has('promocode'))
        {
            $promocode = Promocode::whereCode($request->get('promocode'))->first();

            if($promocode && $promocode->expire_at > \Carbon\Carbon::now()){

                $this->total_before_promocode = $this->total;
                $this->promocode_discount = $this->total * ($promocode->discount / 100);
                $this->promocode_discount_percentage = $promocode->discount;

                $this->total = $this->total - $this->promocode_discount;

                $this->serviceFeeTotal = $this->total - $this->ownerTotal;

                $this->serviceFee = $this->serviceFeeTotal / (1 + $this->taxPercentage/100);

                $this->taxOnServiceFee = $this->serviceFeeTotal - $this->serviceFee;

                // onda računica ide ovako:
                // od ukupnog oduzmi discount
                // od discounted price sracunaj koliko ostaje ( service fee + tax)
                // onda sracunaj koliko je service fee a koliko je
            }
        }

        // Minimum stay check // ToDo

        return [
            'promocode'=> $request->has('promocode')
                ? $request->get('promocode')
                : null,
            'start' => $this->start->format('Y-m-d'),
            'end' => $this->end->format('Y-m-d'),
            'guests'=> $this->guests,
            'parking'=> $this->parking,
            'nights' => count($this->dates),
            'weeklyDiscount' => $this->weeklyDiscount,
            'monthlyDiscount' => $this->monthlyDiscount,

            'baseAvg' => round($this->baseAvg, 2),
            'baseTotal' => round($this->baseTotal, 2),
            'extraGuestFee' => round($this->extraGuestFee, 2),
            'discount'=> round($this->discount, 2),
            'sericeFeePercentage' => $this->sericeFeePercentage,
            'taxPercentage'       => $this->taxPercentage,
            'total'=> round($this->total, 2),
            'ownerTotal'=> round($this->ownerTotal, 2),
            'parkingTotal'=> round($this->parkingTotal, 2),
            'serviceFee'=> round($this->serviceFee, 2),
            'taxOnServiceFee'=> round($this->taxOnServiceFee, 2),
            'serviceFeeTotal'=> round($this->serviceFeeTotal, 2),
            'avgPerNight'=> round(
                $this->total / count($this->dates)
            , 2),
            'promocode_discount_percentage' => round($this->promocode_discount_percentage,2),
            'promocode_discount' => round($this->promocode_discount,2),
            'total_before_promocode' => round($this->total_before_promocode,2),

            'pricing'=> [
                "charge_after_guest" => $this->pricing->charge_after_guest,
                "price_per_guest" => $this->pricing->price_per_guest,
                "guests" => $this->pricing->guests,
                "price" => $this->pricing->price,
                "price_weekend" => $this->pricing->price_weekend,
                "weekly_discount" => $this->pricing->weekly_discount,
                "monthly_discount" => $this->pricing->monthly_discount,
                "min_stay" => $this->pricing->min_stay,

                'min_stay_monday' => $this->pricing->min_stay_monday,
                'min_stay_tuesday' => $this->pricing->min_stay_tuesday,
                'min_stay_wednesday' => $this->pricing->min_stay_wednesday,
                'min_stay_thursday' => $this->pricing->min_stay_thursday,
                'min_stay_friday' => $this->pricing->min_stay_friday,
                'min_stay_saturday' => $this->pricing->min_stay_saturday,
                'min_stay_sunday' => $this->pricing->min_stay_sunday,

                // Parking
                'parking_available'=> $this->pricing->parking['available'],
                'parking_price_type'=> $this->pricing->parking['price_type'],
                'parking_price'=> $this->pricing->parking['price'],

                'sericeFeePercentage' => $this->sericeFeePercentage,
                'taxPercentage'       => $this->taxPercentage,
            ],
            'dates' => $this->dates,
        ];
    }

    // ✅
    public static function calculateTotalBasedOnBasePriceAndFee($base, $fee, $tax)
    {
        $fee = $fee / 100;
        $tax = $tax / 100;

        return $base / (1 - $fee * (1 + $tax));
    }

    public function calculateCustomPrice(Apartment $apartment, Request $request) {
        $data = $request->validate([
            'total' => 'required|numeric|min:3|max:999999',
        ]);

        $commission = Commission::resolveServiceFeeForApartment($apartment);

        return self::calculateCustomPriceBasedOnTotalAndFee(
            $data['total'],
            $commission['sericeFeePercentage'],
            $commission['taxPercentage']
        );
    }

    // ✅
    public static function calculateCustomPriceBasedOnTotalAndFee($total, $fee, $tax)
    {
        $fee = $fee / 100;
        $tax = $tax / 100;

        $serviceFee = $total * $fee;
        $tax = $serviceFee * $tax;
        $ownerTotal = $total - $serviceFee - $tax;

        return [
            'total' => round($total, 2),
            'serviceFee' => round($serviceFee, 2),
            'taxOnServiceFee' => round($tax, 2),
            'ownerTotal' => round($ownerTotal, 2),
        ];
    }
}
