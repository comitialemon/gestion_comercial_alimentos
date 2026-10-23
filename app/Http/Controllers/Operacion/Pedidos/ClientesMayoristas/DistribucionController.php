<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Transportadora;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Envio;
use App\Models\Operacion\Pedidos\ClientesMayoristas\HistorialPedido;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DistribucionController extends Controller
{
    // ============================================================
    // VISTA: Bandeja de Distribución (Para Cámara, Chóferes y Cliente)
    // ============================================================
    public function index()
    {
        $clienteId = session('cliente_id');

        // 1. Pedidos Pendientes (listos para armar)
        $pedidosPendientes = PedidoCliente::where('IdCliente', $clienteId)
            ->where('EstadoPedido', 'Pendiente')
            ->with(['cliente', 'sucursal'])
            ->orderBy('FechaPedido', 'asc')
            ->get();

        // 2. Pedidos En Cámara
        $pedidosEnCamara = PedidoCliente::where('IdCliente', $clienteId)
            ->where('EstadoPedido', 'En Cámara')
            ->with(['cliente', 'sucursal'])
            ->orderBy('FechaPedido', 'asc')
            ->get();

        // 3. Pedidos En Distribución
        $pedidosEnDistribucion = PedidoCliente::where('IdCliente', $clienteId)
            ->where('EstadoPedido', 'En Distribución')
            ->with(['cliente', 'sucursal'])
            ->orderBy('FechaPedido', 'asc')
            ->get();

        // 4. Pedidos En Transportadora
        $pedidosEnTransportadora = PedidoCliente::where('IdCliente', $clienteId)
            ->where('EstadoPedido', 'En Transportadora')
            ->with(['cliente', 'sucursal', 'envio.transportadora'])
            ->orderBy('FechaPedido', 'desc')
            ->get();

        // 5. Listado de transportadoras
        $transportadoras = Transportadora::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->orderBy('Nombre')
            ->get();

        return Inertia::render('Operacion/ClientesMayoristas/Distribucion/Index', [
            'pedidosPendientes' => $pedidosPendientes,
            'pedidosEnCamara' => $pedidosEnCamara,
            'pedidosEnDistribucion' => $pedidosEnDistribucion,
            'pedidosEnTransportadora' => $pedidosEnTransportadora,
            'transportadoras' => $transportadoras,
        ]);
    }

    // ============================================================
    // API: Cambiar Estado
    // ============================================================
    public function cambiarEstado(Request $request, $id)
    {
        // 🔥 Aceptamos "Recibido" como estado final
        $request->validate([
            'nuevo_estado' => 'required|in:En Cámara,En Distribución,En Transportadora,Recibido',
            'id_transportadora' => 'nullable|required_if:nuevo_estado,En Transportadora|exists:pedidos_clientes_transportadoras,IdTransportadora',
            'numero_guia' => 'nullable|string|max:100',
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

            // Actualizar el pedido
            $pedido->update([
                'EstadoPedido' => $nuevoEstado,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            // Si pasa a Transportadora, CREAR EL ENVÍO
            if ($nuevoEstado === 'En Transportadora') {
                Envio::create([
                    'IdPedidoCliente' => $pedido->IdPedidoCliente,
                    'IdTransportadora' => $request->input('id_transportadora'),
                    'NumeroGuia' => $request->input('numero_guia'),
                    'FechaEnvio' => now(),
                    'IdOperadorDespacha' => $operadorId,
                    'Observaciones' => $request->input('observacion'),
                ]);
            }

            // 🔥 Si el cliente marca "Recibido", actualizamos la fecha real del envío
            if ($nuevoEstado === 'Recibido') {
                $envio = Envio::where('IdPedidoCliente', $pedido->IdPedidoCliente)->first();
                if ($envio) {
                    $envio->update(['FechaEntregaReal' => now()]);
                }
            }

            // Guardar en el Historial (Para la barra del cliente)
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

    // ============================================================
    // API: Obtener Historial
    // ============================================================
    public function getHistorial($id)
    {
        try {
            $clienteId = session('cliente_id');

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with(['envio.transportadora', 'historial'])
                ->first();

            if (!$pedido) {
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado.'], 404);
            }

            return response()->json([
                'success' => true,
                'estado_actual' => $pedido->EstadoPedido,
                'historial' => $pedido->historial,
                'envio' => $pedido->envio,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // CRUD: Transportadoras
    // ============================================================
    public function guardarTransportadora(Request $request)
    {
        $request->validate([
            'Nombre' => 'required|string|max:100',
            'Telefono' => 'nullable|string|max:50',
            'Destino' => 'nullable|string|max:100',
        ]);

        $clienteId = session('cliente_id');

        try {
            $trans = Transportadora::create([
                'IdCliente' => $clienteId,
                'Nombre' => $request->Nombre,
                'Telefono' => $request->Telefono,
                'Destino' => $request->Destino,
                'ActivoInactivo' => 1,
            ]);

            return response()->json(['success' => true, 'transportadora' => $trans]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}