<?php

use Illuminate\Support\Facades\Schedule;

// One email a week per linked parent, on the day they chose.
Schedule::command('reports:weekly')->weeklyOn(0, '18:00');
Schedule::command('reports:weekly --day=friday')->fridays()->at('18:00');
Schedule::command('reports:weekly --day=monday')->mondays()->at('07:00');
