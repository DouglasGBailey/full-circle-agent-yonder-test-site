<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home');
    }

    public function rooms(): View
    {
        return view('pages.rooms');
    }

    public function restaurant(): View
    {
        return view('pages.restaurant');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function sitemap(): Response
    {
        $base = rtrim(config('app.url'), '/');
        $paths = ['/', '/rooms', '/restaurant', '/contact', '/channel', '/board'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($paths as $p) {
            $xml .= '  <url><loc>'.e($base.$p).'</loc></url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
