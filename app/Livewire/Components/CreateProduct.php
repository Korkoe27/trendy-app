<?php

namespace App\Livewire\Components;

use App\Models\{Categories,Product};
use Livewire\Attributes\Validate;
use Livewire\Component;

class CreateProduct extends Component
{
        public $showModal = false;


        #[Validate('required')]
        public $name = '';

        #[Validate('required')]
        public $category_id ='';
        
        public $sku = '';
        public $cost_price = '';
        public $selling_price = '';
        public $units_per_box = '';


        public function save(){
            $this->validate();
            Product::create($this->all());
            return $this->redirect('products');
        }
        
        public function render()
        {
            $categories = Categories::get();
            return view('livewire.components.create-product',
                [
                    'categories' => $categories,
                ]);
        }
}
