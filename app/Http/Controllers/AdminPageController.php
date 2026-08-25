<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class AdminPageController extends Controller
{
    public function index(): View
    {
        return view('admin.index');
    }
}
