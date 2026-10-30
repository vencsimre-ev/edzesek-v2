<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\ConvertsBudapestDates;

class ListTrainingSessionsRequest extends \App\Http\Requests\ListTrainingSessionsRequest
{
    use ConvertsBudapestDates;
}
