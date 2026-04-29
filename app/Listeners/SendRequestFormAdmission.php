<?php

namespace App\Listeners;

use App\Events\RequestEmailAdmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRequestFormAdmission
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(RequestEmailAdmission $event): void
    {
        //
    }
}
