<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\ConvertsBudapestDates;

class StoreTrainingSessionRequest extends \App\Http\Requests\StoreTrainingSessionRequest
{
    use ConvertsBudapestDates;
}
