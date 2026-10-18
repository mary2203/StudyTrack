<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebDashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        return view('dashboard', [
            'user' => $request->user(),
            'dashboard' => $dashboard->forUser($request->user()),
        ]);
    }
}
