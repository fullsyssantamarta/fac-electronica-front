<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Acta de Entrega - Productos Farmacéuticos y de Salud</title>
    </head>
    <body>
        <div>
            <h2 align="center" class="title"><strong>ACTA DE ENTREGA - PRODUCTOS FARMACÉUTICOS Y DE SALUD</strong></h2>
        </div>
        <br>
        
        <div style="margin-top:20px; margin-bottom:15px;">
            <table>
                <tr>
                    <td><strong>Institución de Salud:</strong></td>
                    <td>{{$company->name}}</td>
                    <td><strong>Fecha de Documento:</strong></td>
                    <td>{{$document->date_of_issue->format('d/m/Y')}}</td>
                </tr>
                <tr>
                    <td><strong>NIT:</strong></td>
                    <td>{{$company->number}}</td>
                    <td><strong>Fecha de Entrega:</strong></td>
                    <td>{{date('d/m/Y')}}</td>
                </tr>
                <tr>
                    <td><strong>Sede/Establecimiento:</strong></td>
                    <td>{{$establishment->address}} - {{$establishment->department->description ?? ''}} - {{$establishment->district->description ?? ''}}</td>
                    <td><strong>Hora:</strong></td>
                    <td>{{date('H:i:s')}}</td>
                </tr>
            </table>
        </div>
        <br>

        <div style="margin-bottom:15px;">
            <table>
                <tr>
                    <td><strong>Orden de Compra:</strong></td>
                    <td>{{$document->series}}-{{$document->number}}</td>
                </tr>
                <tr>
                    <td><strong>Proveedor/Laboratorio:</strong></td>
                    <td>{{$document->supplier->name}}</td>
                </tr>
                <tr>
                    <td><strong>NIT Proveedor:</strong></td>
                    <td>{{$document->supplier->number}}</td>
                </tr>
                @if($document->supplier->address)
                <tr>
                    <td><strong>Dirección Proveedor:</strong></td>
                    <td>{{$document->supplier->address}}</td>
                </tr>
                @endif
            </table>
        </div>
        <br>

        <div class="">
            <table class="" style="border: 1px solid black; border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Item</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Código/EAN</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Descripción del Producto</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Presentación</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Lote</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Vencimiento</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Cantidad</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Precio Unit.</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Total</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Estado</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Observaciones</strong></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($document->items as $key => $item)
                    <tr>
                        <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ $key + 1 }}</td>
                        <td style="border: 1px solid black; padding: 5px;">{{ $item->item->internal_id ?? $item->item->id }}</td>
                        <td style="border: 1px solid black; padding: 5px;">{{ $item->item->description }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ $item->item->unit_type->description ?? 'UND' }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ $item->lot_code ?? ($item->item->lot_code ?? 'N/A') }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: center;">
                            @if(isset($item->date_of_due) && $item->date_of_due)
                                {{ $item->date_of_due->format('d/m/Y') }}
                            @elseif(isset($item->item->date_of_due) && $item->item->date_of_due)
                                {{ $item->item->date_of_due->format('d/m/Y') }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ number_format($item->quantity, 0) }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: right;">{{ $document->currency_type_id }} {{ number_format($item->unit_price, 2) }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: right;">{{ $document->currency_type_id }} {{ number_format($item->total, 2) }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: center;">CONFORME</td>
                        <td style="border: 1px solid black; padding: 5px;"></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <br>

        <div style="margin-top:20px;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 70%;"></td>
                    <td style="width: 30%;">
                        <table style="border: 1px solid black; border-collapse: collapse; width: 100%;">
                            <tr>
                                <td style="border: 1px solid black; padding: 5px;"><strong>Subtotal:</strong></td>
                                <td style="border: 1px solid black; padding: 5px; text-align: right;">{{ $document->currency_type_id }} {{ number_format($document->total_taxed, 2) }}</td>
                            </tr>
                            @if($document->total_igv > 0)
                            <tr>
                                <td style="border: 1px solid black; padding: 5px;"><strong>IVA:</strong></td>
                                <td style="border: 1px solid black; padding: 5px; text-align: right;">{{ $document->currency_type_id }} {{ number_format($document->total_igv, 2) }}</td>
                            </tr>
                            @endif
                            <tr style="background-color: #f0f0f0;">
                                <td style="border: 1px solid black; padding: 5px;"><strong>TOTAL:</strong></td>
                                <td style="border: 1px solid black; padding: 5px; text-align: right;"><strong>{{ $document->currency_type_id }} {{ number_format($document->total, 2) }}</strong></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <br><br>

        <div style="margin-top:30px;">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 50%; text-align: center; border-top: 1px solid black; padding-top: 5px;">
                        <strong>ENTREGADO POR</strong><br>
                        {{$document->supplier->name}}<br>
                        <small>Representante del Laboratorio/Proveedor</small><br>
                        Firma, Nombre y Cédula
                    </td>
                    <td style="width: 50%; text-align: center; border-top: 1px solid black; padding-top: 5px;">
                        <strong>RECIBIDO POR</strong><br>
                        {{$company->name}}<br>
                        <small>Responsable de Farmacia/Almacén</small><br>
                        Firma, Nombre y Cédula
                    </td>
                </tr>
            </table>
        </div>
        <br><br>

        <div style="margin-top:20px;">
            <table style="width: 100%; border: 1px solid black; border-collapse: collapse;">
                <tr style="background-color: #f0f0f0;">
                    <td style="border: 1px solid black; padding: 8px; text-align: center;" colspan="2">
                        <strong>CONDICIONES DE ALMACENAMIENTO Y OBSERVACIONES</strong>
                    </td>
                </tr>
                <tr>
                    <td style="border: 1px solid black; padding: 20px; height: 80px; vertical-align: top;">
                        <strong>Condiciones de almacenamiento verificadas:</strong><br>
                        □ Temperatura ambiente (15°C - 25°C)<br>
                        □ Refrigeración (2°C - 8°C)<br>
                        □ Congelación (-15°C a -25°C)<br>
                        □ Protegido de la luz<br>
                        □ Lugar seco<br><br>
                        <strong>Observaciones adicionales:</strong><br>
                        <!-- Espacio para observaciones manuales -->
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-top:20px; font-size: 10px; text-align: center; color: #666;">
            <p>Acta de entrega farmacéutica generada automáticamente el {{date('d/m/Y H:i:s')}}</p>
            <p><em>Este documento certifica la recepción conforme de productos farmacéuticos y de salud</em></p>
        </div>
    </body>
</html>
