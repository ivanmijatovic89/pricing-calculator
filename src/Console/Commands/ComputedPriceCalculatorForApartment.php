<?php

namespace Bookaweb\PricingCalculator\Console\Commands;

use App\Ads\Types\Apartment;
use Illuminate\Console\Command;
use Bookaweb\PricingCalculator\Controllers\PriceCalculatorController;
use Illuminate\Http\Request;
use App\Ads\ApartmentComputedPricing;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Bookaweb\PricingCalculator\Requests\CalculatePriceRequest;

class ComputedPriceCalculatorForApartment extends Command
{
    protected $signature = 'compute-price-for-apartment {apartmentId}';
    protected $description = 'Compute Price for Single Apartment';

    public $apartment;

    public function handle()
    {
        $timerStart = microtime(true);

        try {
            $this->apartment = Apartment::findOrFail($this->argument('apartmentId'));
        } catch (ModelNotFoundException $exception) {
            throw new ModelNotFoundException('Apartment do not exists ID ' . $this->argument('apartmentId'));
        }
        $start = \Carbon\Carbon::now();

        // Initialize the array to store records that will be bulk inserted
        $bulkInsertArray = [];

        $total = [
            'price' => [],
            'weekly_discount' => [],
            'monthly_discount' => [],
        ];

        ApartmentComputedPricing::where('ad_id', $this->apartment->id)->delete();

        for ($i=0; $i < PriceCalculatorController::DAYS_IN_ADVANCE_TO_CALCULATE ; $i++) { // day

            $results = $this->getPrice(
                $start->format('Y-m-d'),
                $start->clone()->addDay()->format('Y-m-d')
            );

            for ($guests=1; $guests <= $this->apartment->detail->guests ; $guests++)
            {
                if(!isset($total['price'][$guests])){
                    $total['price'][$guests] = 0;
                }
                if(!isset($total['weekly_discount'][$guests])){
                    $total['weekly_discount'][$guests] = 0;
                }
                if(!isset($total['monthly_discount'][$guests])){
                    $total['monthly_discount'][$guests] = 0;
                }

                $res = $results[$guests];

                $bulkInsertArray[] = [
                    'ad_id' => $this->apartment->id,
                    'date'=> $start->format('Y-m-d'),
                    'guests' => $guests,
                    'total' => $res['price']['total'],
                    'total_weekly_discount' => $res['weekly_discount']['total'],
                    'total_monthly_discount' => $res['monthly_discount']['total'],
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now(),
                ];

                $total['price'][$guests] += $res['price']['total'];
                $total['weekly_discount'][$guests] += $res['weekly_discount']['total'];
                $total['monthly_discount'][$guests] += $res['monthly_discount']['total'];
            }
            $start->addDay();
        }

        $chunks = array_chunk($bulkInsertArray, 10);

        \DB::transaction(function () use ($chunks) {
            foreach ($chunks as $chunk) {
                ApartmentComputedPricing::insert($chunk);
            }
        });

        $timerEnd = microtime(true);
        $duration = $timerEnd - $timerStart;
        $this->info("Time taken: " . round($duration,2) . " seconds.");
    }

    public function getPrice($start, $end){
        $priceCalculator = new PriceCalculatorController();
        $data = [
            'start' => $start,
            'end' => $end,
            'guests' => 1,
            'parking' => 0,
        ];

        $request = new Request($data);
        $calculatePriceRequest = CalculatePriceRequest::createFrom($request);

        return $priceCalculator->computedCalculatePrice($this->apartment->id, $calculatePriceRequest);
    }
}
