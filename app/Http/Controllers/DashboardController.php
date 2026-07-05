<?php

namespace App\Http\Controllers;

use App\Services\DashboardEstadisticas;

class DashboardController extends Controller
{
    public function index(DashboardEstadisticas $dashboardEstadisticas)
    {
        return view('dashboard', [
            'datos' => $dashboardEstadisticas->resumen(),
        ]);
    }
}
