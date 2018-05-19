<?php

namespace Pheye\Payments\Http\Controllers;
use Carbon\Carbon;

class TimezoneController extends Controller
{
    public function index($timezone)
    {
        return Carbon::now($timezone)->toDateTimeString();
    }
}
