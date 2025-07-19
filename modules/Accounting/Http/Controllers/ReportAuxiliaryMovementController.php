<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryDetail;
use Modules\Accounting\Helpers\AccountBalanceHelper;
use Modules\Factcolombia1\Models\Tenant\Company;
use Modules\Factcolombia1\Models\Tenant\TypeIdentityDocument;
use Carbon\Carbon;
use Mpdf\Mpdf;

class ReportAuxiliaryMovementController extends Controller
{
    public function index()
    {
        return view('accounting::reports.auxiliary_movement');
    }

    public function records(Request $request)
    {
        $dateStart = $request->input('date_start', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $dateEnd = $request->input('date_end', now()->endOfMonth()->toDateString()) . ' 23:59:59';

        $accounts = JournalEntryDetail::whereBetween('created_at', [$dateStart, $dateEnd])
            ->with(['chartOfAccount', 'journalEntry'])
            ->get()
            ->map(function ($detail) {
                $account = $detail->chartOfAccount;
                $entry = $detail->journalEntry;
                // centralizo la obtencion de datos del documento
                $documentInfo = $this->getDocumentInfo($entry);

                return [
                    'account_id' => $account->id,
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'document_info' => $documentInfo,
                    'date' => $entry->date,
                    'debit' => $detail->debit,
                    'credit' => $detail->credit,
                    'description' => $entry->description,
                ];
            })
            ->groupBy('account_code')
                ->map(function ($items, $account_code) use ($request) {
                $totalDebit = $items->sum('debit');
                $totalCredit = $items->sum('credit');
                $accountName = $items->first()['account_name'] ?? '';
                $date = Carbon::parse($request->date_start)->format('Y-m-d');
                $accountId = $items->first()['account_id'];
                $balanceInitial = AccountBalanceHelper::getBalanceUpTo($accountId, $date);

                return [
                    'account_code' => $account_code,
                    'account_name' => $accountName,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'details' => $items->values(),
                    'balance_initial' => $balanceInitial ?? 0,
                    'balance_final' => ($balanceInitial ?? 0) + $totalDebit - $totalCredit,
                ];
            })
            ->values();

        return response()->json([
            'data' => $accounts,
            'message' => 'Movimientos auxiliares obtenidos correctamente.',
        ]);
    }

    /**
     * Centraliza la lógica para obtener los campos relevantes según el tipo de documento.
     */
    private function getDocumentInfo($entry)
    {
        if ($entry->purchase) {
            $purchase = $entry->purchase;
            return [
                'type' => 'purchase',
                'id' => $purchase->id,
                'number' => $purchase->series . '-' . $purchase->number,
                'third_party_number' => $purchase->supplier->number,
                'third_party_name' => $purchase->supplier->name,
            ];
        }
        if ($entry->document) {
            $document = $entry->document;
            return [
                'type' => 'document',
                'id' => $document->id,
                'number' => $document->prefix . '-' . $document->number,
                'third_party_number' => $document->customer->number,
                'third_party_name' => $document->customer->name,
            ];
        }
        // TO DO - inventory - extra...
        return null;
    }

    public function export(Request $request)
    {
        $format = $request->input('format', 'pdf');

        if ($format === 'pdf') {
            return $this->exportPdf($request);
        } elseif ($format === 'excel') {
            return $this->exportExcel($request);
        } else {
            return response()->json(['error' => 'Formato no soportado'], 400);
        }
    }

    private function exportPdf(Request $request)
    {
        // Reutilizar la lógica de records para obtener los datos
        $dateStart = $request->date_start;
        $dateEnd = $request->date_end;
        $data = $this->records($request)->getData(true);

        // Renderizar la vista como HTML
        $html = view('accounting::pdf.auxiliary_movement', [
            'accounts' => $data['data'],
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ])->render();

        // Configurar mPDF
        $mpdf = new Mpdf(['orientation' => 'L']);
        $mpdf->SetHeader('Reporte de Movimientos auxiliares');
        $mpdf->SetFooter('Generado el ' . now()->format('Y-m-d H:i:s'));
        $mpdf->WriteHTML($html);

        // Descargar el PDF
        return $mpdf->Output('reporte_movimientos_auxiliares.pdf', 'I'); // 'I' para mostrar en el navegador
    }

    private function exportExcel(Request $request)
    {
        // Reutilizar la lógica de records para obtener los datos
        $data = $this->records($request)->getData(true);
        $company = Company::first();
        $document_type = TypeIdentityDocument::find($company->type_identity_document_id);

        // Crear el archivo Excel
        $filename = 'reporte_situacion_financiera.xlsx';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', $company->name);
        $sheet->setCellValue('A2', $document_type->name);
        $sheet->setCellValue('B2', $company->identification_number);
        $sheet->setCellValue('A3', 'DIRECCIÓN');
        $sheet->setCellValue('B3', $company->address);

        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');
        $sheet->setCellValue('A5', 'Fechas');
        $sheet->setCellValue('B5', ' del ' . ($dateStart ?? '-') . ' al ' . ($dateEnd ?? '-'));


        // Configurar encabezados
        $sheet->setCellValue('A7', 'Código');
        $sheet->setCellValue('B7', 'Cuenta');
        $sheet->setCellValue('C7', 'Comprobante');
        $sheet->setCellValue('D7', 'Número de documento');
        $sheet->setCellValue('E7', 'Nombre del tercero');
        $sheet->setCellValue('F7', 'Descripción');
        $sheet->setCellValue('G7', 'Saldo inicial');
        $sheet->setCellValue('H7', 'Débito');
        $sheet->setCellValue('I7', 'Crédito');
        $sheet->setCellValue('J7', 'Saldo final');

        // Agregar datos de cuentas
        $totalDebit = 0;
        $totalCredit = 0;
        $row = 8;
        foreach ($data['data'] as $group) {
            $row++;
            $sheet->setCellValue('A' . $row, 'Cuenta contable:');
            $sheet->setCellValue('B' . $row, $group['account_code']);
            $sheet->setCellValue('G' . $row, $group['balance_initial']);
            $sheet->setCellValue('H' . $row, $group['total_debit']);
            $sheet->setCellValue('I' . $row, $group['total_credit']);
            $sheet->setCellValue('J' . $row, $group['balance_final']);
            $totalDebit += $group['total_debit'];
            $totalCredit += $group['total_credit'];
            foreach($group['details'] as $detail) {
                $row++;
                $sheet->setCellValue('A' . $row, $detail['account_code']);
                $sheet->setCellValue('B' . $row, $detail['account_name']);
                $sheet->setCellValue('C' . $row, $detail['document_info']['type'] ?? '');
                $sheet->setCellValue('D' . $row, $detail['document_info']['number'] ?? '');
                $sheet->setCellValue('E' . $row, $detail['document_info']['third_party_name'] ?? '');
                $sheet->setCellValue('F' . $row, $detail['description']);
                $sheet->setCellValue('G' . $row, '0');
                $sheet->setCellValue('H' . $row, $detail['debit']);
                $sheet->setCellValue('I' . $row, $detail['credit']);
                $sheet->setCellValue('J' . $row, '0');
            }
        }
        $row++;
        // Agregar última línea con suma de totales
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('H' . $row, $totalDebit);
        $sheet->setCellValue('I' . $row, $totalCredit);

        // Descargar el archivo Excel
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tempFile);

        // debug
        // $dataArray = $sheet->toArray();
        // echo '<table border="1">';
        // foreach ($dataArray as $row) {
        //     echo '<tr>';
        //     foreach ($row as $cell) {
        //         echo '<td>' . htmlspecialchars($cell) . '</td>';
        //     }
        //     echo '</tr>';
        // }
        // echo '</table>';
        // exit;

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}
