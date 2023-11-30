<?php

namespace Bookaweb\PricingCalculator\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePriceRequest extends FormRequest
{
    public function rules()
    {
        return [
            'start'=>'required|date_format:Y-m-d',
            'end'=>'required|date_format:Y-m-d',
            'guests'=>'required|numeric',
            'parking'=> 'required|numeric',

            'overwrite.guests' => 'integer|min:1|max:50',
            'overwrite.price' => 'integer|min:1|max:99999',
            'overwrite.price_weekend' => 'integer|min:1|max:99999',
            'overwrite.charge_after_guest' => 'integer',
            'overwrite.price_per_guest' => 'integer',

            'overwrite.weekly_discount'  => 'integer|min:0|max:99',
            'overwrite.monthly_discount' => 'integer|min:0|max:99',

            'promocode'=> 'alpha_num|nullable',
        ];
    }
}
