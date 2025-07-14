<?php

namespace App\Livewire\Components;

use Livewire\Component;

class Sidebar extends Component
{
    public $isOpen = false;
    public $activeItem = null;

    public function toggleSidebar()
    {
        $this->isOpen = !$this->isOpen;
    }

    public function closeSidebar()
    {
        $this->isOpen = false;
    }

    public function setActiveItem($item)
    {
        $this->activeItem = $item;
    }

    public function render()
    {
        return view('livewire.components.sidebar');
    }
}