<div>
    {{-- A good traveler has no fixed plans and is not intent upon arriving. --}}
    <section class="">
        <h1 class="capitalize">
            create a category for your products.
        </h1>
        <h5 class="">and select how products under this category will be priced</h5>
        <form action="" class="" wire:submit="save">
            <div class="">
                <label for="name" class="">
                    Name
                </label>
            </div>
            <div class="">
                <label for="pricing_model" class="capitalize">
                    pricing model
                </label>
                <select name="pricing_model" id="">
                    <option disabled selected value="">select a pricing model</option>
                    <option value="per_unit" class="">Per Unit (Drinks and snacks)</option>
                    <option value="per_plate" class="">Per Plate (Food)</option>
                    <option value="per_slot" class="">Per Slot (Games and Events)</option>
                    <option value="flat_fee" class="">Per Fee (Others...)</option>
                </select>
            </div>
            <div class="">
                <button type="submit" class="">Save</button>
            </div>
        </form>
    </section>
</div>
