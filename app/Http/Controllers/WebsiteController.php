<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function asset(string $filename): BinaryFileResponse
    {
        $file = public_path('assets/'.$filename);
        abort_unless(is_file($file), 404);

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function pageImage(string $filename): BinaryFileResponse
    {
        $file = public_path('images/page/'.$filename);
        abort_unless(is_file($file), 404);

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
