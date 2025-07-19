<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Acta de Entrega - Compra</title>
    </head>
    <body>
        <div>
            <h2 align="center" class="title"><strong>ACTA DE ENTREGA - COMPRA</strong></h2>
        </div>
        <br>
        
        <div style="margin-top:20px; margin-bottom:15px;">
            <table>
                <tr>
                    <td><strong>Empresa:</strong></td>
                    <td>{{$company->name}}</td>
                    <td><strong>Fecha de Documento:</strong></td>
                    <td>{{$document->date_of_issue->format('d/m/Y')}}</td>
                </tr>
                <tr>
                    <td><strong>RUT:</strong></td>
                    <td>{{$company->number}}</td>
                    <td><strong>Fecha de Entrega:</strong></td>
                    <td>{{date('d/m/Y')}}</td>
                </tr>
                <tr>
                    <td><strong>Establecimiento:</strong></td>
                    <td>{{$establishment->address}} - {{$establishment->department->description}} - {{$establishment->district->description}}</td>
                    <td><strong>Hora:</strong></td>
                    <td>{{date('H:i:s')}}</td>
                </tr>
            </table>
        </div>
        <br>

        <div style="margin-bottom:15px;">
            <table>
                <tr>
                    <td><strong>Documento de Compra:</strong></td>
                    <td>{{$document->series}}-{{$document->number}}</td>
                </tr>
                <tr>
                    <td><strong>Proveedor:</strong></td>
                    <td>{{$document->supplier->name}}</td>
                </tr>
                <tr>
                    <td><strong>RUC/DNI Proveedor:</strong></td>
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
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Código</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Descripción</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Unidad</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Cantidad</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Precio Unit.</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Total</strong></th>
                        <th style="border: 1px solid black; padding: 8px; text-align: center;"><strong>Estado Entrega</strong></th>
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
                        <td style="border: 1px solid black; padding: 5px; text-align: center;">{{ number_format($item->quantity, 2) }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: right;">{{ $document->currency_type_id }} {{ number_format($item->unit_price, 2) }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: right;">{{ $document->currency_type_id }} {{ number_format($item->total, 2) }}</td>
                        <td style="border: 1px solid black; padding: 5px; text-align: center;">RECIBIDO</td>
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
                                <td style="border: 1px solid black; padding: 5px;"><strong>IGV:</strong></td>
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
                        Firma y sello
                    </td>
                    <td style="width: 50%; text-align: center; border-top: 1px solid black; padding-top: 5px;">
                        <strong>RECIBIDO POR</strong><br>
                        {{$company->name}}<br>
                        Firma y sello
                    </td>
                </tr>
            </table>
        </div>
        <br><br>

        <div style="margin-top:20px;">
            <table style="width: 100%; border: 1px solid black; border-collapse: collapse;">
                <tr style="background-color: #f0f0f0;">
                    <td style="border: 1px solid black; padding: 8px; text-align: center;" colspan="2">
                        <strong>OBSERVACIONES GENERALES</strong>
                    </td>
                </tr>
                <tr>
                    <td style="border: 1px solid black; padding: 20px; height: 80px; vertical-align: top;">
                        <!-- Espacio para observaciones manuales -->
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-top:20px; font-size: 10px; text-align: center; color: #666;">
            <p>Documento generado automáticamente el {{date('d/m/Y H:i:s')}}</p>
        </div>
    </body>
</html>
