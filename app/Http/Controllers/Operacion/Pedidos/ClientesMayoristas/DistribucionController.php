<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Transportadora;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Envio;
use App\Models\Operacion\Pedidos\ClientesMayoristas\HistorialPedido;
use App\Models\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente\BilleteraOperador;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DistribucionController extends Controller
{
    // ============================================================
    // VISTA 1: CÁMARA / REPARTO (Pendiente → En Distribución)
    // ============================================================
    // ============================================================
    // VISTA 1A: REPARTO CIUDAD (Pendiente + Ciudad=true)
    // ============================================================
    public function indexRepartoCiudad()
    {
        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $pedidos = $this->pedidosConOperadorYZona('Pendiente', $clienteId, $sucursalId);

        // Solo los que tienen zona Ciudad
        $pedidosPendientes = $pedidos->filter(fn ($p) => $p->Ciudad === true)->values();

        // indexRepartoCiudad
        return Inertia::render('Operacion/ClientesMayoristas/Distribucion/RepartoCiudad', [
            'pedidosPendientes' => $pedidosPendientes,
        ]);
    }

    // ============================================================
    // VISTA 1B: REPARTO PROVINCIA (Pendiente + Provincia=true)
    // ============================================================
    public function indexRepartoProvincia()
    {
        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $pedidos = $this->pedidosConOperadorYZona('Pendiente', $clienteId, $sucursalId);

        // Solo los que tienen zona Provincia
        $pedidosPendientes = $pedidos->filter(fn ($p) => $p->Provincia === true)->values();

        // indexRepartoProvincia
        return Inertia::render('Operacion/ClientesMayoristas/Distribucion/RepartoProvincia', [
            'pedidosPendientes' => $pedidosPendientes,
        ]);
    }

    // ============================================================
    // VISTA 2: DESPACHO (En Distribución → En Transportadora)
    // ============================================================
    public function indexDespacho()
    {
        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        // Pedidos En Distribución
        $pedidosEnDistribucion = $this->pedidosConOperadorYZona('En Distribución', $clienteId, $sucursalId);

        // Pedidos En Transportadora
        $pedidosTransportadoraRaw = PedidoCliente::where('pedidos_clientes.IdCliente', $clienteId)
            ->where('pedidos_clientes.IdSucursal', $sucursalId)
            ->whereRaw('TRIM(EstadoPedido) = ?', ['En Transportadora'])
            ->with(['cliente', 'sucursal', 'envio.transportadora.identificador'])
            ->orderBy('pedidos_clientes.FechaPedido', 'desc')
            ->get();

        $pedidosEnTransportadora = $pedidosTransportadoraRaw->map(function ($pedido) use ($clienteId) {
            return $this->decorarPedido($pedido, $clienteId);
        });

        // ✅ NUEVO: Pedidos Recibidos (finalizados)
        $pedidosRecibidosRaw = PedidoCliente::where('pedidos_clientes.IdCliente', $clienteId)
            ->where('pedidos_clientes.IdSucursal', $sucursalId)
            ->whereRaw('TRIM(EstadoPedido) = ?', ['Recibido'])
            ->with(['cliente', 'sucursal', 'envio.transportadora.identificador'])
            ->orderBy('pedidos_clientes.FechaActualiza', 'desc')  // los más recientes primero
            ->limit(100)  // opcional: no cargar toda la historia
            ->get();

        $pedidosRecibidos = $pedidosRecibidosRaw->map(function ($pedido) use ($clienteId) {
            return $this->decorarPedido($pedido, $clienteId);
        });

        $transportadoras = Transportadora::with('identificador')
            ->where('ActivoInactivo', 1)
            ->get();

        return Inertia::render('Operacion/ClientesMayoristas/Distribucion/Despacho', [
            'pedidosEnDistribucion'   => $pedidosEnDistribucion,
            'pedidosEnTransportadora' => $pedidosEnTransportadora,
            'pedidosRecibidos'        => $pedidosRecibidos,   // ✅ NUEVO
            'transportadoras'         => $transportadoras,
        ]);
    }

    // ============================================================
    // HELPER COMPARTIDO
    // ============================================================
    private function pedidosConOperadorYZona(string $estado, $clienteId, $sucursalId)
    {
        $pedidos = PedidoCliente::where('pedidos_clientes.IdCliente', $clienteId)
            ->where('pedidos_clientes.IdSucursal', $sucursalId)
            ->where('EstadoPedido', $estado)
            ->with(['cliente', 'sucursal'])
            ->orderBy('pedidos_clientes.FechaPedido', 'asc')
            ->get();

        return $pedidos->map(function ($pedido) use ($clienteId) {
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $pedido->IdOperador)
                ->first(['todos_identificador.Nombre']);

            $pedido->OperadorNombre = $operador->Nombre ?? 'Sin operador';

            $asignacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_operadores_clientes as opc')
                ->join('todos_operador_sucursaldb as osdb', 'osdb.IdSucursalDB', '=', 'opc.IdSucursalDB')
                ->where('opc.IdOperador', $pedido->IdOperador)
                ->where('opc.IdCliente', $clienteId)
                ->where('osdb.IdSucursal', $pedido->IdSucursal)
                ->first(['opc.Ciudad', 'opc.Provincia']);

            $pedido->Ciudad    = $asignacion ? (bool) $asignacion->Ciudad : false;
            $pedido->Provincia = $asignacion ? (bool) $asignacion->Provincia : false;

            return $pedido;
        });
    }
    
    private function decorarPedido($pedido, $clienteId)
    {
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $pedido->IdOperador)
            ->first(['todos_identificador.Nombre']);

        $pedido->OperadorNombre = $operador->Nombre ?? 'Sin operador';

        $asignacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_operadores_clientes as opc')
            ->join('todos_operador_sucursaldb as osdb', 'osdb.IdSucursalDB', '=', 'opc.IdSucursalDB')
            ->where('opc.IdOperador', $pedido->IdOperador)
            ->where('opc.IdCliente', $clienteId)
            ->where('osdb.IdSucursal', $pedido->IdSucursal)
            ->first(['opc.Ciudad', 'opc.Provincia']);

        $pedido->Ciudad    = $asignacion ? (bool) $asignacion->Ciudad : false;
        $pedido->Provincia = $asignacion ? (bool) $asignacion->Provincia : false;

        return $pedido;
    }
    // ============================================================
    // CAMBIAR ESTADO (valida transición por modo)
    // ============================================================
    public function cambiarEstado(Request $request, $id)
    {
        $request->validate([
            'modo'           => 'nullable|in:reparto,despacho',
            'nuevo_estado'   => 'required|in:En Distribución,En Transportadora,Recibido',
            'numero_guia'    => 'nullable|string|max:100',
            'costo_envio'    => 'nullable|numeric|min:0',
            'observacion'    => 'nullable|string|max:255',
        ]);

        $modo        = $request->input('modo', 'despacho');
        $nuevoEstado = $request->input('nuevo_estado');

        $permitidas = match ($modo) {
            'reparto'  => ['En Distribución'],
            'despacho' => ['En Transportadora', 'Recibido'],
            default    => [],
        };

        if (!in_array($nuevoEstado, $permitidas, true)) {
            return response()->json([
                'success' => false,
                'message' => "Transición '{$nuevoEstado}' no permitida en el módulo '{$modo}'.",
            ], 422);
        }

        $operadorId = session('operador_id');
        $clienteId  = session('cliente_id');
        $conexion   = DB::connection('mysql_gestion_comercial_alimentos');

        try {
            $conexion->beginTransaction();

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->lockForUpdate()
                ->first();

            if (!$pedido) {
                $conexion->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado.'
                ], 404);
            }

            if ($nuevoEstado === 'En Transportadora') {
                $asignacion = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('operacion_pedidos_operadores_clientes as opc')
                    ->join('todos_operador_sucursaldb as osdb', 'osdb.IdSucursalDB', '=', 'opc.IdSucursalDB')
                    ->where('opc.IdOperador', $pedido->IdOperador)
                    ->where('opc.IdCliente', $clienteId)
                    ->where('osdb.IdSucursal', $pedido->IdSucursal)
                    ->first(['opc.Ciudad']);

                $esCiudad = $asignacion ? (bool) $asignacion->Ciudad : false;

                if (!$esCiudad && !$request->filled('id_transportadora')) {
                    $conexion->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Debes seleccionar una transportadora para envíos a provincia.'
                    ], 422);
                }
            }

            $estadoAnterior = $pedido->EstadoPedido;

            $pedido->update([
                'EstadoPedido'        => $nuevoEstado,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza'      => now(),
            ]);

            if ($nuevoEstado === 'En Transportadora') {
                $costoEnvio = (float) $request->input('costo_envio', 0);

                Envio::create([
                    'IdPedidoCliente'     => $pedido->IdPedidoCliente,
                    'IdTransportadora'    => $request->input('id_transportadora'),
                    'NumeroGuia'          => $request->input('numero_guia'),
                    'CostoEnvio'          => $costoEnvio,
                    'FechaEnvio'          => now(),
                    'IdOperadorDespacha'  => $operadorId,
                    'Observaciones'       => $request->input('observacion'),
                ]);

                if ($costoEnvio > 0) {
                    $billeteraCliente = BilleteraOperador::where('IdOperador', $pedido->IdOperador)
                        ->where('IdCliente', $pedido->IdCliente)
                        ->where('IdSucursal', $pedido->IdSucursal)
                        ->where('ActivoInactivo', 0)
                        ->lockForUpdate()
                        ->first();

                    if ($billeteraCliente && (float) $billeteraCliente->SaldoActual >= $costoEnvio) {
                        $billeteraCliente->cobrarEnvio(
                            $costoEnvio,
                            $pedido->IdPedidoCliente,
                            "Cobro Envío - Pedido #{$pedido->NumeroPedido}",
                            $operadorId
                        );

                        Cache::forget(
                            "billetera_operador_{$billeteraCliente->IdOperador}_{$billeteraCliente->IdCliente}_{$billeteraCliente->IdSucursal}"
                        );

                        Log::info("✅ Costo de envío descontado", [
                            'IdPedido'    => $pedido->IdPedidoCliente,
                            'Monto'       => $costoEnvio,
                            'IdBilletera' => $billeteraCliente->IdBilletera,
                        ]);
                    } else {
                        Log::warning("Pedido despachado sin cobrar envío (falta de saldo/billetera)", [
                            'IdPedido' => $pedido->IdPedidoCliente,
                            'Operador' => $pedido->IdOperador,
                            'Cliente'  => $pedido->IdCliente,
                            'Sucursal' => $pedido->IdSucursal,
                            'Costo'    => $costoEnvio,
                        ]);
                    }
                }
            }

            if ($nuevoEstado === 'Recibido') {
                $envio = Envio::where('IdPedidoCliente', $pedido->IdPedidoCliente)->first();
                if ($envio) {
                    $envio->update(['FechaEntregaReal' => now()]);
                }
            }

            HistorialPedido::create([
                'IdPedidoCliente' => $pedido->IdPedidoCliente,
                'EstadoAnterior'  => $estadoAnterior,
                'EstadoNuevo'     => $nuevoEstado,
                'IdOperador'      => $operadorId,
                'Observacion'     => $request->input('observacion'),
                'FechaCambio'     => now(),
            ]);

            $conexion->commit();

            return response()->json([
                'success' => true,
                'message' => "Estado actualizado a '{$nuevoEstado}' correctamente.",
            ]);

        } catch (\Exception $e) {
            $conexion->rollBack();
            Log::error('Error en distribucion: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // HISTORIAL
    // ============================================================
    public function getHistorial($id)
    {
        try {
            $clienteId = session('cliente_id');
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with(['envio.transportadora.identificador'])
                ->first();

            if (!$pedido) {
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado.'], 404);
            }

            $historial = HistorialPedido::where('IdPedidoCliente', $id)
                ->orderBy('FechaCambio', 'asc')
                ->get();

            return response()->json([
                'success'       => true,
                'estado_actual' => $pedido->EstadoPedido,
                'historial'     => $historial,
                'envio'         => $pedido->envio,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // BUSCAR IDENTIFICADORES
    // ============================================================
    public function buscarIdentificadores(Request $request)
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        try {
            $identificadores = Identificador::where('Nombre', 'like', "%{$q}%")
                ->orWhere('CI_NIT', 'like', "%{$q}%")
                ->orderBy('Nombre')
                ->limit(15)
                ->get(['IdIdentificador', 'Nombre', 'CI_NIT']);

            $idsYaTransportadora = Transportadora::whereIn(
                'IdIdentificador',
                $identificadores->pluck('IdIdentificador')
            )->pluck('IdIdentificador')->toArray();

            return response()->json(
                $identificadores->map(fn ($i) => [
                    'IdIdentificador'   => $i->IdIdentificador,
                    'Nombre'            => $i->Nombre,
                    'CI_NIT'            => $i->CI_NIT,
                    'YaEsTransportadora'=> in_array($i->IdIdentificador, $idsYaTransportadora),
                ])
            );
        } catch (\Exception $e) {
            Log::error('Error buscando identificadores: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // GUARDAR TRANSPORTADORA
    // ============================================================
    public function guardarTransportadora(Request $request)
    {
        $request->validate([
            'IdIdentificador' => 'nullable|integer',
            'Nombre'          => 'required_without:IdIdentificador|string|max:100',
            'CI_NIT'          => 'nullable|numeric',
        ]);

        $operadorId = session('operador_id');
        $conexion   = DB::connection('mysql_gestion_comercial_alimentos');

        try {
            $conexion->beginTransaction();

            if ($request->filled('IdIdentificador')) {
                $identificador = Identificador::find($request->IdIdentificador);

                if (!$identificador) {
                    $conexion->rollBack();
                    return response()->json(['success' => false, 'message' => 'Identificador no encontrado.'], 404);
                }

                $yaExiste = Transportadora::where('IdIdentificador', $identificador->IdIdentificador)->exists();
                if ($yaExiste) {
                    $conexion->rollBack();
                    return response()->json(['success' => false, 'message' => 'Ese identificador ya está registrado como transportadora.'], 422);
                }
            } else {
                $identificador = null;
                if ($request->filled('CI_NIT')) {
                    $identificador = Identificador::where('CI_NIT', $request->CI_NIT)->first();
                }

                if (!$identificador) {
                    $ciNit = $request->filled('CI_NIT') ? (int) $request->CI_NIT : mt_rand(1000000, 9999999);
                    $identificador = Identificador::create([
                        'Nombre'            => $request->Nombre,
                        'CI_NIT'            => $ciNit,
                        'IdOperadorIngreso' => (int) ($operadorId ?? 1),
                        'FechaIngreso'      => now(),
                        'IdOperadorEdita'   => (int) ($operadorId ?? 1),
                        'FechaEdita'        => now(),
                    ]);
                }
            }

            $trans = Transportadora::create([
                'IdIdentificador' => $identificador->IdIdentificador,
                'ActivoInactivo'  => 1,
            ]);

            $conexion->commit();
            $trans->load('identificador');

            return response()->json([
                'success'        => true,
                'message'        => 'Transportadora creada correctamente.',
                'transportadora' => $trans,
            ]);
        } catch (\Exception $e) {
            $conexion->rollBack();
            Log::error('Error creando transportadora: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}