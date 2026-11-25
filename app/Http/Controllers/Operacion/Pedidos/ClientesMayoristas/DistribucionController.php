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
    // VISTA 1A: REPARTO CIUDAD (3 tabs: Pendientes / En Tránsito / Finalizados)
    // ============================================================
    public function indexRepartoCiudad()
    {
        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $pendientes = $this->pedidosConOperadorYZona('Pendiente', $clienteId, $sucursalId)
            ->filter(fn ($p) => $p->Ciudad === true)->values();

        $enTransito = $this->pedidosConOperadorYZonaMultiple(
            ['En Distribución', 'En Transportadora'],
            $clienteId, $sucursalId
        )->filter(fn ($p) => $p->Ciudad === true)->values();

        $finalizados = $this->pedidosConOperadorYZona('Recibido', $clienteId, $sucursalId)
            ->filter(fn ($p) => $p->Ciudad === true)->values();

        return Inertia::render('Operacion/ClientesMayoristas/Distribucion/RepartoCiudad', [
            'pedidosPendientes'  => $pendientes,
            'pedidosEnTransito'  => $enTransito,
            'pedidosFinalizados' => $finalizados,
        ]);
    }

    // ============================================================
    // VISTA 1B: REPARTO PROVINCIA (3 tabs)
    // ============================================================
    public function indexRepartoProvincia()
    {
        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $pendientes = $this->pedidosConOperadorYZona('Pendiente', $clienteId, $sucursalId)
            ->filter(fn ($p) => $p->Provincia === true)->values();

        $enTransito = $this->pedidosConOperadorYZonaMultiple(
            ['En Distribución', 'En Transportadora'],
            $clienteId, $sucursalId
        )->filter(fn ($p) => $p->Provincia === true)->values();

        $finalizados = $this->pedidosConOperadorYZona('Recibido', $clienteId, $sucursalId)
            ->filter(fn ($p) => $p->Provincia === true)->values();

        return Inertia::render('Operacion/ClientesMayoristas/Distribucion/RepartoProvincia', [
            'pedidosPendientes'  => $pendientes,
            'pedidosEnTransito'  => $enTransito,
            'pedidosFinalizados' => $finalizados,
        ]);
    }

    // ============================================================
    // HELPER: múltiples estados a la vez (con operador y zona)
    // ============================================================
    private function pedidosConOperadorYZonaMultiple(array $estados, $clienteId, $sucursalId)
    {
        $pedidos = PedidoCliente::where('pedidos_clientes.IdCliente', $clienteId)
            ->where('pedidos_clientes.IdSucursal', $sucursalId)
            ->whereIn('EstadoPedido', $estados)
            ->with(['cliente', 'sucursal', 'envios.transportadora.identificador'])
            ->orderBy('pedidos_clientes.FechaPedido', 'desc')
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

    // ============================================================
    // VISTA 2: DESPACHO
    // ============================================================
    public function indexDespacho()
    {
        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $pedidosEnDistribucion = $this->pedidosConOperadorYZona('En Distribución', $clienteId, $sucursalId);

        $pedidosTransportadoraRaw = PedidoCliente::where('pedidos_clientes.IdCliente', $clienteId)
            ->where('pedidos_clientes.IdSucursal', $sucursalId)
            ->whereRaw('TRIM(EstadoPedido) = ?', ['En Transportadora'])
            ->with(['cliente', 'sucursal', 'envios.transportadora.identificador'])
            ->orderBy('pedidos_clientes.FechaPedido', 'desc')
            ->get();

        $pedidosEnTransportadora = $pedidosTransportadoraRaw->map(function ($pedido) use ($clienteId) {
            return $this->decorarPedido($pedido, $clienteId);
        });

        $pedidosRecibidosRaw = PedidoCliente::where('pedidos_clientes.IdCliente', $clienteId)
            ->where('pedidos_clientes.IdSucursal', $sucursalId)
            ->whereRaw('TRIM(EstadoPedido) = ?', ['Recibido'])
            ->with(['cliente', 'sucursal', 'envios.transportadora.identificador'])
            ->orderBy('pedidos_clientes.FechaActualiza', 'desc')
            ->limit(100)
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
            'pedidosRecibidos'        => $pedidosRecibidos,
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
    // CAMBIAR ESTADO (N guías + 1 solo cobro por pedido)
    // ============================================================
    public function cambiarEstado(Request $request, $id)
    {
        $request->validate([
            'modo'                      => 'nullable|in:reparto,despacho',
            'nuevo_estado'              => 'required|in:En Distribución,En Transportadora,Recibido',
            'observacion'               => 'nullable|string|max:255',
            'guias'                     => 'nullable|array|min:1',
            'guias.*.id_transportadora' => 'required|integer|exists:pedidos_clientes_transportadoras,IdTransportadora',
            'guias.*.numero_guia'       => 'nullable|string|max:100',
            'guias.*.costo_envio'       => 'nullable|numeric|min:0',
            'guias.*.observacion'       => 'nullable|string|max:255',
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
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado.'], 404);
            }

            $guias = collect($request->input('guias', []))
                ->filter(fn ($g) => !empty($g['id_transportadora']))
                ->values();

            if ($nuevoEstado === 'En Transportadora') {
                $asignacion = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('operacion_pedidos_operadores_clientes as opc')
                    ->join('todos_operador_sucursaldb as osdb', 'osdb.IdSucursalDB', '=', 'opc.IdSucursalDB')
                    ->where('opc.IdOperador', $pedido->IdOperador)
                    ->where('opc.IdCliente', $clienteId)
                    ->where('osdb.IdSucursal', $pedido->IdSucursal)
                    ->first(['opc.Ciudad']);

                $esCiudad = $asignacion ? (bool) $asignacion->Ciudad : false;

                if (!$esCiudad && $guias->isEmpty()) {
                    $conexion->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Debes agregar al menos una guía con transportadora para envíos a provincia.'
                    ], 422);
                }
            }

            $estadoAnterior = $pedido->EstadoPedido;

            $pedido->update([
                'EstadoPedido'        => $nuevoEstado,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza'      => now(),
            ]);

            // ============================================================
            // Crear N envíos (cada uno con su guía, costo y observación)
            // ============================================================
            if ($nuevoEstado === 'En Transportadora' && $guias->isNotEmpty()) {
                foreach ($guias as $guia) {
                    Envio::create([
                        'IdPedidoCliente'    => $pedido->IdPedidoCliente,
                        'IdTransportadora'   => $guia['id_transportadora'],
                        'NumeroGuia'         => $guia['numero_guia'] ?? null,
                        'CostoEnvio'         => (float) ($guia['costo_envio'] ?? 0),
                        'FechaEnvio'         => now(),
                        'IdOperadorDespacha' => $operadorId,
                        'Observaciones'      => $guia['observacion'] ?? null,
                    ]);
                }

                // ============================================================
                // ✅ UN SOLO cobro a la billetera por el TOTAL de todas las guías
                // ============================================================
                $totalCosto = $guias->sum(fn ($g) => (float) ($g['costo_envio'] ?? 0));

                if ($totalCosto > 0) {
                    $billeteraCliente = BilleteraOperador::where('IdOperador', $pedido->IdOperador)
                        ->where('IdCliente', $pedido->IdCliente)
                        ->where('IdSucursal', $pedido->IdSucursal)
                        ->where('ActivoInactivo', 0)
                        ->lockForUpdate()
                        ->first();

                    if ($billeteraCliente && (float) $billeteraCliente->SaldoActual >= $totalCosto) {
                        $billeteraCliente->cobrarEnvio(
                            $totalCosto,
                            $pedido->IdPedidoCliente,
                            "Cobro Envío - Pedido #{$pedido->NumeroPedido} - {$guias->count()} guía(s)",
                            $operadorId
                        );

                        Cache::forget(
                            "billetera_operador_{$billeteraCliente->IdOperador}_{$billeteraCliente->IdCliente}_{$billeteraCliente->IdSucursal}"
                        );

                        Log::info("✅ Costo de envío descontado (agrupado)", [
                            'IdPedido'    => $pedido->IdPedidoCliente,
                            'Total'       => $totalCosto,
                            'Guias'       => $guias->count(),
                            'IdBilletera' => $billeteraCliente->IdBilletera,
                        ]);
                    } else {
                        Log::warning("Pedido despachado sin cobrar envío (falta de saldo/billetera)", [
                            'IdPedido' => $pedido->IdPedidoCliente,
                            'Total'    => $totalCosto,
                            'Guias'    => $guias->count(),
                            'Operador' => $pedido->IdOperador,
                            'Cliente'  => $pedido->IdCliente,
                            'Sucursal' => $pedido->IdSucursal,
                        ]);
                    }
                }
            }

            // ============================================================
            // Recibido: marcar TODAS las guías con FechaEntregaReal
            // ============================================================
            if ($nuevoEstado === 'Recibido') {
                Envio::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                    ->whereNull('FechaEntregaReal')
                    ->update(['FechaEntregaReal' => now()]);
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
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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
                ->with(['envios.transportadora.identificador'])
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
                'envios'        => $pedido->envios,
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