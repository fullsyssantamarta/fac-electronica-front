<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PurchaseActaExport implements FromView, ShouldAutoSize
{
    use Exportable;
    
    private $document;
    private $company;
    private $establishment;
    
    public function __construct($document, $company, $establishment)
    {
        $this->document = $document;
        $this->company = $company;
        $this->establishment = $establishment;
    }
    
    public function view(): View
    {
        return view('tenant.purchases.acta_excel', [
            'document' => $this->document,
            'company' => $this->company,
            'establishment' => $this->establishment
        ]);
    }
}
