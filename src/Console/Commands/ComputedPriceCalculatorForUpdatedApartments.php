<?php

namespace Bookaweb\PricingCalculator\Console\Commands;

use Illuminate\Console\Command;
// use App\Ads\Types\Apartment;
// use App\Ads\PendingApartmentForComputedPricing;
use Bookaweb\PricingCalculator\Controllers\PriceCalculatorController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        // PendingApartmentForComputedPricing::
        \DB::table('pending_apartments_for_computed_pricing')
            ->where('update_after', '<', \Carbon\Carbon::now())
            ->orderBy('update_after', 'asc')
            ->chunk(200, function ($records)
            {
                foreach ($records as  $record) {
                    try {
                        // $apartment = Apartment::findOrFail($record->ad_id);
                        $priceCalculatorController = new PriceCalculatorController();
                        $apartment = $priceCalculatorController->findApartment($record->ad_id);
                        if (!$apartment) {
                            throw new ModelNotFoundException('Apartment for update with ID ' . $this->argument('apartmentId') . ' do not exists');
                        }

                        $this->total++;
                        $this->info($this->total.'. '.$apartment->name. ' #'.$apartment->id);
                        $this->call('compute-price-for-apartment', [
                            'apartmentId' => $apartment->id
                        ]);
                        \DB::table('pending_apartments_for_computed_pricing')->where('id', $record->id)->delete();
                        // $record->delete();
                    } catch (\Exception $e) {
                        $message = "Error processing apartment (update) ID " . $record->ad_id;
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
