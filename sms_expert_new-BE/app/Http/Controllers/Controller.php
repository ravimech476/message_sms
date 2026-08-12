<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Artisan;

class Controller extends BaseController {

    use AuthorizesRequests,
        ValidatesRequests;

    public $appName = 'SMS Expert';

    public function __construct() {
        // Any initialization code here
    }

}
