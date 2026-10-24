<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Transportadora;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Envio;
use App\Models\Operacion\Pedidos\ClientesMayoristas\HistorialPedido;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DistribucionController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');

        $pedidosPendientes = PedidoCliente::where('IdCliente', $clienteId)
            ->where('EstadoPedido', 'Pendiente')
            ->with(['cliente', 'sucursal'])
            ->orderBy('FechaPedido', 'asc')
            ->get();

        $pedidosEnCamara = PedidoCliente::where('IdCliente', $clienteId)
            ->where('EstadoPedido', 'En Cámara')
            ->with(['cliente', 'sucursal'])
            ->orderBy('FechaPedido', 'asc')
            ->get();

        $pedidosEnDistribucion = PedidoCliente::where('IdCliente', $clienteId)
            ->where('EstadoPedido', 'En Distribución')
            ->with(['cliente', 'sucursal'])
            ->orderBy('FechaPedido', 'asc')
            ->get();

        $pedidosEnTransportadora = PedidoCliente::where('IdCliente', $clienteId)
            ->where('EstadoPedido', 'En Transportadora')
            ->with(['cliente', 'sucursal', 'envio.transportadora.identificador'])
            ->orderBy('FechaPedido', 'desc')
            ->get();

        $transportadoras = Transportadora::with('identificador')
            ->where('ActivoInactivo', 1)
            ->get();

        return Inertia::render('Operacion/ClientesMayoristas/Distribucion/Index', [
            'pedidosPendientes' => $pedidosPendientes,
            'pedidosEnCamara' => $pedidosEnCamara,
            'pedidosEnDistribucion' => $pedidosEnDistribucion,
            'pedidosEnTransportadora' => $pedidosEnTransportadora,
            'transportadoras' => $transportadoras,
        ]);
    }

        public function cambiarEstado(Request $request, $id)
    {
        $request->validate([
            'nuevo_estado' => 'required|in:En Cámara,En Distribución,En Transportadora,Recibido',
            'id_transportadora' => 'nullable|required_if:nuevo_estado,En Transportadora|exists:pedidos_clientes_transportadoras,IdTransportadora',
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
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado.'], 404);
            }

            $estadoAnterior = $pedido->EstadoPedido;
            $nuevoEstado = $request->input('nuevo_estado');

            $pedido->update([
                'EstadoPedido' => $nuevoEstado,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            if ($nuevoEstado === 'En Transportadora') {
                $costoEnvio = (float) $request->input('costo_envio', 0);

                // 1. Creamos el registro del Envío
                Envio::create([
                    'IdPedidoCliente' => $pedido->IdPedidoCliente,
                    'IdTransportadora' => $request->input('id_transportadora'),
                    'NumeroGuia' => $request->input('numero_guia'),
                    'CostoEnvio' => $costoEnvio,
                    'FechaEnvio' => now(),
                    'IdOperadorDespacha' => $operadorId,
                    'Observaciones' => $request->input('observacion'),
                ]);

                // 2. 🔥 DESCONTAR EL ENVÍO SI TIENE BILLETERA Y SALDO
                if ($costoEnvio > 0) {
                    $billeteraCliente = \App\Models\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente\BilleteraOperador::where('IdOperador', $pedido->IdOperador)
                        ->where('ActivoInactivo', 0)
                        ->lockForUpdate()
                        ->first();

                    if ($billeteraCliente && (float)$billeteraCliente->SaldoActual >= $costoEnvio) {
                        // Tiene saldo suficiente: descontamos
                        $billeteraCliente->cobrarEnvio(
                            $costoEnvio,
                            $pedido->IdPedidoCliente,
                            "Cobro Envío - Pedido #{$pedido->NumeroPedido}",
                            $operadorId
                        );
                        
                        Log::info("✅ Costo de envío descontado", [
                            'IdPedido' => $pedido->IdPedidoCliente,
                            'Monto' => $costoEnvio
                        ]);
                    } else {
                        // No tiene billetera o no tiene saldo: 
                        // Por ahora dejamos pasar (Caso 2 se hará después)
                        Log::warning("Pedido despachado sin cobrar envío (falta de saldo/billetera)", [
                            'IdPedido' => $pedido->IdPedidoCliente,
                            'Operador' => $pedido->IdOperador,
                            'Costo' => $costoEnvio
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
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado.'], 404);
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
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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
                $ciNit = $request->filled('CI_NIT') ? (int)$request->CI_NIT : mt_rand(1000000, 9999999);

                // 🔥 AGREGADOS FechaIngreso y FechaEdita
                $identificador = Identificador::create([
                    'Nombre' => $request->Nombre,
                    'CI_NIT' => $ciNit,
                    'IdOperadorIngreso' => (int)($operadorId ?? 1),
                    'FechaIngreso' => now(),        
                    'IdOperadorEdita' => (int)($operadorId ?? 1),
                    'FechaEdita' => now(),          
                ]);
            }

            $trans = Transportadora::create([
                'IdIdentificador' => $identificador->IdIdentificador,
                'ActivoInactivo' => 1,
            ]);

            $conexion->commit();

            $trans->load('identificador');

            return response()->json(['success' => true, 'transportadora' => $trans]);
        } catch (\Exception $e) {
            $conexion->rollBack();
            Log::error('Error al crear transportadora', [
                'message' => $e->getMessage(),
                'operadorId' => $operadorId,
                'payload' => $request->all()
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}