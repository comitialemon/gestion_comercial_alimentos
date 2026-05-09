<?php
// app/Http/Controllers/ContextoPdvController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContextoPdvController extends Controller
{
    private function f() { return DB::connection('facturacion'); }

    public function index(Request $request)
    {
        $empresaId = session('empresa_id_facturacion');
        $sucursalId = session('sucursal_id_facturacion');
        
        if (!$empresaId || !$sucursalId) {
            return redirect()->route('contexto.index')
                ->with('error', 'No hay mapeo a facturación para esta empresa/sucursal.');
        }

        $empresa = $this->f()->table('empresa')
            ->select('idEmpresa', 'nombre', 'nit', 'ambiente', 'modalidad')
            ->where('idEmpresa', $empresaId)
            ->first();

        $sucursal = $this->f()->table('sucursal')
            ->select('idSucursal', 'nombre', 'codigo')
            ->where('idSucursal', $sucursalId)
            ->first();

        return Inertia::render('Contexto/PuntoVenta', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'selected' => ['punto_venta_id' => (int)(session('punto_venta_id') ?? 0)],
        ]);
    }

    public function lista(Request $request)
    {
        $sucursalId = (int) session('sucursal_id_facturacion');
        
        if ($sucursalId <= 0) {
            return response()->json([]);
        }

        $pdvs = $this->f()->table('punto_venta')
            ->where('idSucursal', $sucursalId)
            ->where('activo', 1)
            ->orderBy('codigo')
            ->select('idPuntoVenta as id', 'codigo', 'nombre', 'direccion as descripcion')
            ->get();

        return response()->json($pdvs);
    }

    public function store(Request $request)
    {
        $request->validate(['punto_venta_id' => 'required|integer']);

        $sucursalId = (int) session('sucursal_id_facturacion');
        
        $pdv = $this->f()->table('punto_venta')
            ->where('idPuntoVenta', $request->punto_venta_id)
            ->where('idSucursal', $sucursalId)
            ->where('activo', 1)
            ->first();

        if (!$pdv) {
            return back()->withErrors(['punto_venta_id' => 'Punto de venta inválido para esta sucursal.']);
        }

        session([
            'punto_venta_id' => (int) $pdv->idPuntoVenta,
            'punto_venta_codigo' => $pdv->codigo,
            'punto_venta_nombre' => $pdv->nombre,
        ]);

        return redirect()->route('oficial.index')->with('success', 'Punto de venta seleccionado.');
    }
}