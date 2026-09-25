<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class WebsiteController extends Controller
{
    public function index(): View
    {
        return view('website.index');
    }

    public function privacy(): View
    {
        return view('website.privacy');
    }
}
