<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\EstadisticasTenants;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function __construct(
        private readonly EstadisticasTenants $estadisticasTenants,
    ) {}

    public function index(): View
    {
        return view('super_admin.panel.index', [
            'datos' => $this->estadisticasTenants->resumen(),
        ]);
    }
}
