<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Libro Ventas</title>

        @include('report::commons.styles')

        <style>
            @page {
              margin: 10;
            }

            html {
                font-family: sans-serif;
                font-size: 9px;
            }

            table.combined-table {
                width: 100%;
                table-layout: fixed;
                font-size: 8px;
            }

            table.combined-table th,
            table.combined-table td {
                padding: 2px 2px;
                word-break: break-all;
                overflow-wrap: break-word;
                white-space: normal;
                text-align: center;
                font-size: 7px;
                max-width: 60px; 
            }

            .retencion-cell {
                font-size: 7px;
                word-break: break-all;
                overflow-wrap: break-word;
                white-space: normal;
                max-width: 60px;
            }
        </style>
    </head>
    <body>
        @include('report::commons.header')

        <div>
            <p align="left" class="title">
                <strong>
                    Libro Ventas
                    {{ $filters->summary_sales_book ? 'Resumido' : ''}}
                </strong>
            </p>
        </div>

        @include('report::co-sales-book.partials.filters')

        @if($records->count() > 0)
            <div class="">
                <div class="">
                    @if($filters->summary_sales_book)
                        @include('report::co-sales-book.partials.summary')
                    @else
                        @include('report::co-sales-book.partials.general')
                    @endif
                </div>
            </div>
        @else
            <div class="callout callout-info">
                <p><strong>No se encontraron registros.</strong></p>
            </div>
        @endif
    </body>
</html>
