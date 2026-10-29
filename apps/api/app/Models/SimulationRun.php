<?php

namespace App\Models;

class SimulationRun extends Record
{
    protected $table = 'simulator_runs';

    protected function casts(): array
    {
        return ['result' => 'array'];
    }
}
