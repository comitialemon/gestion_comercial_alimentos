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
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id'); // ✅ NUEVO

        $queryPedidos = function ($estado) use ($clienteId, $sucursalId) {
            $pedidos = PedidoCliente::where('pedidos_clientes.IdCliente', $clienteId)
                ->where('pedidos_clientes.IdSucursal', $sucursalId) // ✅ NUEVO: filtrar por sucursal
                ->where('EstadoPedido', $estado)
                ->with(['cliente', 'sucursal'])
                ->orderBy('pedidos_clientes.FechaPedido', 'asc')
                ->get();

            return $pedidos->map(function ($pedido) use ($clienteId) {
                // 1. Nombre del operador
                $operador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_operador')
                    ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                    ->where('todos_operador.IdOperador', $pedido->IdOperador)
                    ->first(['todos_identificador.Nombre']);

                $pedido->OperadorNombre = $operador->Nombre ?? 'Sin operador';

                // ✅ FIX: Ciudad/Provincia con JOIN para comparar IdSucursal real
                $asignacion = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('operacion_pedidos_operadores_clientes as opc')
                    ->join('todos_operador_sucursaldb as osdb', 'osdb.IdSucursalDB', '=', 'opc.IdSucursalDB')
                    ->where('opc.IdOperador', $pedido->IdOperador)
                    ->where('opc.IdCliente', $clienteId)
                    ->where('osdb.IdSucursal', $pedido->IdSucursal) // ✅ Ahora sí coincide
                    ->first(['opc.Ciudad', 'opc.Provincia']);

                $pedido->Ciudad = $asignacion ? (bool) $asignacion->Ciudad : false;
                $pedido->Provincia = $asignacion ? (bool) $asignacion->Provincia : false;

                return $pedido;
            });
        };

        $pedidosPendientes = $queryPedidos('Pendiente');
        $pedidosEnDistribucion = $queryPedidos('En Distribución');

        // Hacemos lo mismo para los que están en transportadora
        $pedidosTransportadoraRaw = PedidoCliente::where('pedidos_clientes.IdCliente', $clienteId)
            ->where('pedidos_clientes.IdSucursal', $sucursalId) // ✅ NUEVO: filtrar por sucursal
            ->where('EstadoPedido', 'En Transportadora')
            ->with(['cliente', 'sucursal', 'envio.transportadora.identificador'])
            ->orderBy('pedidos_clientes.FechaPedido', 'desc')
            ->get();

        $pedidosEnTransportadora = $pedidosTransportadoraRaw->map(function ($pedido) use ($clienteId) {
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $pedido->IdOperador)
                ->first(['todos_identificador.Nombre']);

            $pedido->OperadorNombre = $operador->Nombre ?? 'Sin operador';

            // ✅ FIX: Ciudad/Provincia con JOIN
            $asignacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_operadores_clientes as opc')
                ->join('todos_operador_sucursaldb as osdb', 'osdb.IdSucursalDB', '=', 'opc.IdSucursalDB')
                ->where('opc.IdOperador', $pedido->IdOperador)
                ->where('opc.IdCliente', $clienteId)
                ->where('osdb.IdSucursal', $pedido->IdSucursal) // ✅ Ahora sí coincide
                ->first(['opc.Ciudad', 'opc.Provincia']);

            $pedido->Ciudad = $asignacion ? (bool) $asignacion->Ciudad : false;
            $pedido->Provincia = $asignacion ? (bool) $asignacion->Provincia : false;

            return $pedido;
        });

        $transportadoras = Transportadora::with('identificador')
            ->where('ActivoInactivo', 1)
            ->get();

        return Inertia::render('Operacion/ClientesMayoristas/Distribucion/Index', [
            'pedidosPendientes' => $pedidosPendientes,
            'pedidosEnDistribucion' => $pedidosEnDistribucion,
            'pedidosEnTransportadora' => $pedidosEnTransportadora,
            'transportadoras' => $transportadoras,
        ]);
    }

    public function cambiarEstado(Request $request, $id)
    {
        $request->validate([
            'nuevo_estado' => 'required|in:En Distribución,En Transportadora,Recibido',
            'numero_guia' => 'nullable|string|max:100',
            'costo_envio' => 'nullable|numeric|min:0',
            'observacion' => 'nullable|string|max:255',
        ]);

        $operadorId = session('operador_id');
        $clienteId = session('cliente_id');
        $conexion = DB::connection('mysql_gestion_comercial_alimentos');

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

            $nuevoEstado = $request->input('nuevo_estado');

            // ✅ FIX: Si va a transportadora, verificar si es ciudad o provincia con JOIN
            if ($nuevoEstado === 'En Transportadora') {
                $asignacion = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('operacion_pedidos_operadores_clientes as opc')
                    ->join('todos_operador_sucursaldb as osdb', 'osdb.IdSucursalDB', '=', 'opc.IdSucursalDB')
                    ->where('opc.IdOperador', $pedido->IdOperador)
                    ->where('opc.IdCliente', $clienteId)
                    ->where('osdb.IdSucursal', $pedido->IdSucursal) // ✅ Ahora sí coincide
                    ->first(['opc.Ciudad']);

                $esCiudad = $asignacion ? (bool) $asignacion->Ciudad : false;

                // Si es PROVINCIA, sí o sí necesita transportadora
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
                'EstadoPedido' => $nuevoEstado,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            if ($nuevoEstado === 'En Transportadora') {
                $costoEnvio = (float) $request->input('costo_envio', 0);

                Envio::create([
                    'IdPedidoCliente' => $pedido->IdPedidoCliente,
                    'IdTransportadora' => $request->input('id_transportadora'),
                    'NumeroGuia' => $request->input('numero_guia'),
                    'CostoEnvio' => $costoEnvio,
                    'FechaEnvio' => now(),
                    'IdOperadorDespacha' => $operadorId,
                    'Observaciones' => $request->input('observacion'),
                ]);

                if ($costoEnvio > 0) {
                    // ✅ FIX: Billetera con contexto (operador + cliente + sucursal del pedido)
                    $billeteraCliente = BilleteraOperador::where('IdOperador', $pedido->IdOperador)
                        ->where('IdCliente', $pedido->IdCliente)      // ✅ NUEVO
                        ->where('IdSucursal', $pedido->IdSucursal)    // ✅ NUEVO
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

                        // ✅ Invalidar cache del navbar con contexto
                        Cache::forget(
                            "billetera_operador_{$billeteraCliente->IdOperador}_{$billeteraCliente->IdCliente}_{$billeteraCliente->IdSucursal}"
                        );

                        Log::info("✅ Costo de envío descontado", [
                            'IdPedido' => $pedido->IdPedidoCliente,
                            'Monto' => $costoEnvio,
                            'IdBilletera' => $billeteraCliente->IdBilletera,
                        ]);
                    } else {
                        Log::warning("Pedido despachado sin cobrar envío (falta de saldo/billetera)", [
                            'IdPedido' => $pedido->IdPedidoCliente,
                            'Operador' => $pedido->IdOperador,
                            'Cliente' => $pedido->IdCliente,
                            'Sucursal' => $pedido->IdSucursal,
                            'Costo' => $costoEnvio,
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
                'EstadoAnterior' => $estadoAnterior,
                'EstadoNuevo' => $nuevoEstado,
                'IdOperador' => $operadorId,
                'Observacion' => $request->input('observacion'),
                'FechaCambio' => now(),
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

    public function getHistorial($id)
    {
        try {
            $clienteId = session('cliente_id');
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with(['envio.transportadora.identificador'])
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado.'
                ], 404);
            }

            $historial = HistorialPedido::where('IdPedidoCliente', $id)
                ->orderBy('FechaCambio', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'estado_actual' => $pedido->EstadoPedido,
                'historial' => $historial,
                'envio' => $pedido->envio,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function guardarTransportadora(Request $request)
    {
        $request->validate([
            'Nombre' => 'required|string|max:100',
            'CI_NIT' => 'nullable|numeric',
        ]);

        $operadorId = session('operador_id');
        $conexion = DB::connection('mysql_gestion_comercial_alimentos');

        try {
            $conexion->beginTransaction();
            $identificador = null;
            if ($request->filled('CI_NIT')) {
                $identificador = Identificador::where('CI_NIT', $request->CI_NIT)->first();
            }

            if (!$identificador) {
                $ciNit = $request->filled('CI_NIT') ? (int) $request->CI_NIT : mt_rand(1000000, 9999999);
                $identificador = Identificador::create([
                    'Nombre' => $request->Nombre,
                    'CI_NIT' => $ciNit,
                    'IdOperadorIngreso' => (int) ($operadorId ?? 1),
                    'FechaIngreso' => now(),
                    'IdOperadorEdita' => (int) ($operadorId ?? 1),
                    'FechaEdita' => now(),
                ]);
            }

            $trans = Transportadora::create([
                'IdIdentificador' => $identificador->IdIdentificador,
                'ActivoInactivo' => 1,
            ]);

            $conexion->commit();
            $trans->load('identificador');

            return response()->json([
                'success' => true,
                'transportadora' => $trans
            ]);
        } catch (\Exception $e) {
            $conexion->rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}