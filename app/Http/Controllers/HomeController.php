<?php

namespace App\Http\Controllers;

use App\Support\Setup\SystemStatus;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(SystemStatus $status): View
    {
        return view('home', [
            'status' => $status->summary(),
        ]);
    }
}
