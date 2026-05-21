<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $tipoDocumento }} N° {{ $venta->NumeroFactura }}</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            width: 80mm;
            margin: 0 auto;
            padding: 5px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .empresa {
            font-size: 10pt;
            font-weight: bold;
        }
        .sucursal {
            font-size: 9pt;
        }
        .documento {
            font-size: 11pt;
            font-weight: bold;
            margin: 8px 0;
        }
        .line {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .cliente {
            margin: 8px 0;
        }
        .productos th, .productos td {
            font-size: 8pt;
        }
        .productos th {
            text-align: left;
        }
        .productos td {
            padding: 2px 0;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total {
            font-weight: bold;
            margin-top: 8px;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 7pt;
        }
        .anulado {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: rotate(-45deg);
            font-size: 40pt;
            font-weight: bold;
            color: red;
            opacity: 0.5;
            z-index: 100;
        }
    </style>
</head>
<body>
    @if($venta->IdEstado == 2)
        <div class="anulado">ANULADO</div>
    @endif

    <div class="header">
        <div class="empresa">{{ $empresa->Nombre ?? '' }}</div>
        <div class="sucursal">SUCURSAL {{ $sucursal->NumeroSucursal ?? '' }}</div>
        <div class="sucursal">{{ $sucursal->Direccion ?? '' }}</div>
        <div class="sucursal">Tel.: {{ $sucursal->Telefono ?? '' }} - Cel.: {{ $sucursal->Celular ?? '' }}</div>
        <div class="sucursal">SANTA CRUZ - BOLIVIA</div>
        <div class="line"></div>
        <div class="documento">{{ $tipoDocumento }} N° {{ $venta->NumeroFactura }}</div>
        <div class="line"></div>
    </div>

    <div class="cliente">
        <div>FECHA: {{ date('d/m/Y H:i:s', strtotime($venta->FechaVenta)) }}</div>
        <div>NIT/CI: {{ $venta->NITCliente ?? '0' }}</div>
        <div>Sr.(es): {{ $venta->NombreCliente ?? 'CONSUMIDOR FINAL' }}</div>
    </div>

    <div class="line"></div>

    <table class="productos" width="100%">
        <thead>
            <tr>
                <th width="50%">ARTICULO</th>
                <th width="15%" class="text-right">CANT.</th>
                <th width="17%" class="text-right">P.U.</th>
                <th width="18%" class="text-right">IMP.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto }}</td>
                <td class="text-right">{{ number_format($detalle->unidades, 4, ',', '.') }}</td>
                <td class="text-right">{{ number_format($detalle->preciounidades, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($detalle->totalbolivianos, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="line"></div>

    <div class="total">
        <div>TOTAL FACTURA (Bs): <span class="text-right">{{ number_format($venta->ImporteVenta, 2, ',', '.') }}</span></div>
    </div>

    <div class="line"></div>

    <div>
        @foreach($metodosPago as $pago)
        <div>{{ $pago->Descripcion }}: <span class="text-right">{{ number_format($pago->Bolivianos, 2, ',', '.') }}</span></div>
        @endforeach
    </div>

    <div class="total">
        <div>TOTAL PAGO RECIBIDO: <span class="text-right">{{ number_format($metodosPago->sum('Bolivianos'), 2, ',', '.') }}</span></div>
    </div>

    <div class="line"></div>

    <div class="footer">
        <div>{{ $dosificacion->Actividad ?? '' }}</div>
        <div>{{ $dosificacion->PrimeraLeyenda ?? '' }}</div>
        <div>{{ $dosificacion->SegundaLeyenda ?? '' }}</div>
        <div class="line"></div>
        <div>COD.CONTROL: {{ $venta->CodigoControl }}</div>
        <div>AUTORIZACIÓN: {{ $autorizacion }}</div>
        <div>FECHA LÍMITE: {{ isset($dosificacion) ? date('d/m/Y', strtotime($dosificacion->FechaLimiteEmision)) : '' }}</div>
        <div class="line"></div>
        <div>USUARIO: {{ $operador->Nombre ?? '' }}</div>
        <div>Servicio en: {{ $venta->LugarVenta ?? '' }} -- Ticket Nro: {{ $venta->TicketDia }}</div>
        @if($venta->NombreComisionista)
        <div>COMISIONISTA: {{ $venta->NombreComisionista }}</div>
        @endif
        <div>¡GRACIAS POR SU COMPRA!</div>
    </div>
</body>
</html>