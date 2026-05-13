<?php
// app/Http/Controllers/Facturacion/PuntoVentaController.php - GESTIÓN (CORREGIDO)

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class PuntoVentaController extends Controller
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    /**
     * Home - Lista de puntos de venta
     */
    public function index()
    {
        $resultado = $this->facturacionApi->getPuntosVenta();
        
        return Inertia::render('Facturacion/PuntoVenta/Home', [
            'puntos' => $resultado['data'] ?? [],
            'empresaId' => session('empresa_id_facturacion'),
            'sucursalId' => session('sucursal_id_facturacion'),
            'flash' => session()->only(['success', 'error'])
        ]);
    }

    /**
     * Formulario para crear punto de venta
     */
    public function create()
    {
        $tiposResultado = $this->facturacionApi->getTiposPuntoVenta();
        $empresasResultado = $this->facturacionApi->getEmpresas();
        
        return Inertia::render('Facturacion/PuntoVenta/Create', [
            'empresas' => $empresasResultado['data'] ?? [],
            'tiposPuntoVenta' => $tiposResultado['data'] ?? [],
        ]);
    }

    /**
     * Registrar punto de venta (llama a API de facturación)
     */
    public function store(Request $request)
    {
        Log::info('=== Creando punto de venta desde gestión ===', ['datos' => $request->all()]);
        
        $data = $request->validate([
            'idEmpresa' => 'required|integer',
            'idSucursal' => 'required|integer',
            'idTipoPuntoVenta' => 'required|integer',
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'es_movil' => 'required|boolean',
            'puede_firmar' => 'required|boolean',
            'activo' => 'required|boolean',
        ]);
        
        // ✅ OBTENER EL CÓDIGO SIAT del tipo de punto de venta (NO el ID)
        // Primero, obtener los tipos de punto de venta desde la API
        $tiposResultado = $this->facturacionApi->getTiposPuntoVenta();
        $tipos = $tiposResultado['data'] ?? [];
        
        // Buscar el tipo que tiene el ID seleccionado
        $tipoSeleccionado = null;
        foreach ($tipos as $tipo) {
            if ($tipo['idTipoPuntoVenta'] == $data['idTipoPuntoVenta']) {
                $tipoSeleccionado = $tipo;
                break;
            }
        }
        
        if (!$tipoSeleccionado) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Tipo de punto de venta no encontrado');
        }
        
        // ✅ Usar el CÓDIGO SIAT (1,2,3,4,5,6) NO el ID interno
        $codigoTipoPuntoVenta = (int)($tipoSeleccionado['codigo'] ?? 1);
        
        Log::info('Tipo de punto de venta seleccionado', [
            'id_interno' => $data['idTipoPuntoVenta'],
            'codigo_siat' => $codigoTipoPuntoVenta,
            'nombre' => $tipoSeleccionado['nombre']
        ]);
        
        $nitEmisor = session('cliente_nit');
        
        if (!$nitEmisor) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'No hay empresa seleccionada en el contexto');
        }
        
        $codigoSucursal = session('sucursal_numero');
        
        if (!$codigoSucursal) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'No hay sucursal seleccionada en el contexto');
        }
        
        Log::info('Datos para enviar a facturación', [
            'nit_emisor' => (string) $nitEmisor,
            'codigo_sucursal' => $codigoSucursal,
            'codigo_tipo_punto_venta' => $codigoTipoPuntoVenta,
            'nombre' => $data['nombre']
        ]);
        
        // ✅ Enviar el CÓDIGO SIAT, no el ID interno
        $resultado = $this->facturacionApi->registrarPuntoVenta([
            'nit_emisor' => (string) $nitEmisor,
            'codigo_sucursal' => (int)$codigoSucursal,
            'codigo_tipo_punto_venta' => $codigoTipoPuntoVenta,  // ← AHORA USA EL CÓDIGO (1,2,3,4,5,6)
            'nombre' => $data['nombre'],
            'descripcion' => $data['direccion'] ?? $data['nombre'],
        ]);
        
        Log::info('Respuesta de facturación', ['resultado' => $resultado]);
        
        if (!($resultado['success'] ?? false)) {
            return redirect()->back()
                ->withInput()
                ->with('error', $resultado['message'] ?? 'Error al registrar punto de venta');
        }
        
        session()->forget('global_pdv_id');
        
        return redirect()->route('facturacion.puntos-venta.home')
            ->with('success', '✅ Punto de venta registrado correctamente en el SIN');
    }

    /**
     * AJAX: Obtener sucursales por empresa
     */
    public function sucursales(Request $request)
    {
        $request->validate(['idEmpresa' => 'required|integer']);
        
        $resultado = $this->facturacionApi->getSucursales($request->idEmpresa);
        
        return response()->json([
            'sucursales' => $resultado['data'] ?? []
        ]);
    }
}