<?php

namespace App\Livewire\Components;

use Carbon\Carbon;
use Livewire\Component;

class Clock extends Component
{

    public $time;
    public $date;

    public function mount()
    {
        $this->updateTime();
    }

    public function updateTime()
    {
        $now = Carbon::now();
        $this->time = $now->format('H:i:s');
        $this->date = $now->translatedFormat('l, d F Y');
    }
    public function render()
    {
        return view('livewire.components.clock');
    }
}
