<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:enforce-abandonment-policy')->dailyAt('04:00');
