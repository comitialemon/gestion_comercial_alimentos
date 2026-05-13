<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class MenuTactilCarritoController extends Controller
{
    public function index()
    {
        return Inertia::render('PuntoVenta/MenuTactil/Carrito');
    }
}