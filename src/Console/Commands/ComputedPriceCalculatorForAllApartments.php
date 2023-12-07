<?php

namespace Bookaweb\PricingCalculator\Console\Commands;

use Illuminate\Console\Command;
// use App\Ads\Types\Apartment;
// use App\Ads\Category;

class ComputedPriceCalculatorForAllApartments extends Command
{
    protected $signature = 'compute-price-for-all-apartments';
    protected $description = 'Compute Price for ALL Apartment';

    public $total;

    public function handle()
    {
        $timerStart = microtime(true);
        $this->total = 0;
        // $categoryApartment = Category::whereConst('apartment')->first();
        $categoryApartment = \DB::table('ad_categories')
                               ->where('const', 'apartment')
                               ->first();

        // chunk all apartments
        \DB::table('ads')
            ->where('category_id', $categoryApartment->id)
            ->where(function($query) {
                $query->where('active->sr', 1)
                    ->orWhere('active->en', 1);
            })
            ->where('status', 'listed')
            ->orderBy('id')
            ->chunk(200, function ($apartments)
            {
                foreach ($apartments as $i => $apartment) {
                    $this->total++;
                    $this->info($this->total.'. '.$apartment->name. ' #'.$apartment->id);

                    try {
                        // call artisan command to compute price for each appartment
                        $this->call('compute-price-for-apartment', [
                            'apartmentId' => $apartment->id
                        ]);
                    } catch (\Exception $e) {
                        $message = "Error processing apartment ID " . $apartment->id;
                        $this->error($message); // Displaying in the console

                        // Logging into the computed_price_calculator.log
                        \Log::channel('computed_price_calculator')->info($message);
                        \Log::channel('computed_price_calculator')->info($e);
                    }

                }
            });

        $this->info("TOTAL: ".$this->total);

        $timerEnd = microtime(true);
        $duration = $timerEnd - $timerStart;
        $this->info("TOTAL Time taken: " . round($duration,2) . " seconds.");
    }

}
