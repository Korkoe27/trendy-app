<div 
    wire:init="updateTime" 
    wire:poll.1s="updateTime"
    class="w-full gap-10 flex justify-end items-center lg:w-fit text-[#0F51AE] rounded-3xl font-medium"
>
    <span class=" lg:w-fit text-black rounded-3xl font-medium">
        {{ $date }}
    </span>
    <span class="lg:w-fit text-black rounded-3xl font-bold">
        {{ $time }}
    </span>
</div>
