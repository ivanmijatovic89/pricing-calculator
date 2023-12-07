<?php

namespace Bookaweb\PricingCalculator\Console\Commands;

use Illuminate\Console\Command;
use App\Ads\Types\Apartment;
use App\Ads\PendingApartmentForComputedPricing;

class ComputedPriceCalculatorForUpdatedApartments extends Command
{
    protected $signature = 'compute-price-for-updated-apartments';
    protected $description = 'Compute Price for UPDATED Apartments only';

    public $total;

    public function handle()
    {
        $timerStart = microtime(true);
        $this->total = 0;
        // get all pending apartments for price calculator
        PendingApartmentForComputedPricing::
            where('update_after', '<', \Carbon\Carbon::now())
            ->chunk(200, function ($records)
            {
                foreach ($records as  $record) {
                    try {
                        $apartment = Apartment::findOrFail($record->ad_id);
                        $this->total++;
                        $this->info($this->total.'. '.$apartment->name. ' #'.$apartment->id);
                        $this->call('compute-price-for-apartment', [
                            'apartmentId' => $apartment->id
                        ]);
                        $record->delete();
                    } catch (\Exception $e) {
                        $message = "Error processing apartment (update) ID " . $apartment->id;
                        $this->error($message); // Displaying in the console

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
