<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\ConvertsBudapestDates;

class UpdateTrainingSessionRequest extends \App\Http\Requests\UpdateTrainingSessionRequest
{
    use ConvertsBudapestDates;
}
