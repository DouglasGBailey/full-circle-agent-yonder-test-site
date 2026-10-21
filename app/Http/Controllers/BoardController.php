<?php

namespace App\Http\Controllers;

use App\Models\BoardCard;
use Illuminate\View\View;

class BoardController extends Controller
{
    public function index(): View
    {
        $cards = BoardCard::orderByDesc('id')->limit(200)->get();

        return view('board.index', ['cards' => $cards, 'statuses' => BoardCard::STATUSES]);
    }
}
