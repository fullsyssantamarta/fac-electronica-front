<?php

namespace Modules\Purchase\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Person;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\DB;
use Modules\Factcolombia1\Models\Tenant\Company as CoCompany;
use App\Models\Tenant\Company;
use App\Models\Tenant\Warehouse;
use Illuminate\Support\Str;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\CoreFacturalo\Requests\Inputs\Common\EstablishmentInput;
use App\CoreFacturalo\Template;
use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Exception;
use Illuminate\Support\Facades\Mail;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseQuotation;
use Modules\Purchase\Http\Resources\PurchaseOrderCollection;
use Modules\Purchase\Http\Resources\PurchaseOrderResource;
use Modules\Purchase\Mail\PurchaseOrderEmail;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\ChargeDiscountType;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Catalogs\SystemIscType;
use App\Models\Tenant\Catalogs\AttributeType;
use App\Models\Tenant\PaymentMethodType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use Modules\Sale\Models\SaleOpportunity;
use Modules\Factcolombia1\Models\Tenant\{
    Currency,
    Tax,
};
use Modules\Factcolombia1\Models\TenantService\{
    Company as ServiceTenantCompany
};

class PurchaseOrderController extends Controller
{

    use StorageDocument;

    protected $purchase_order;
    protected $company;

    public function index()
    {
        return view('purchase::purchase-orders.index');
    }


    public function create($id = null)
    {
        $sale_opportunity = null;
        return view('purchase::purchase-orders.form', compact('id','sale_opportunity'));
    }

    public function generate($id)
    {
        $purchase_quotation = PurchaseQuotation::with(['items'])->findOrFail($id);

        return view('purchase::purchase-orders.generate', compact('purchase_quotation'));
    }

    public function generateFromSaleOpportunity($id)
    {
        $sale_opportunity = SaleOpportunity::with(['items'])->findOrFail($id);
        $id = null;

        return view('purchase::purchase-orders.form', compact('id','sale_opportunity'));
    }

    public function columns()
    {
        return [
            'date_of_issue' => 'Fecha de emisión'
        ];
    }

    public function records(Request $request)
    {
        $records = PurchaseOrder::where($request->column, 'like', "%{$request->value}%")
                            ->whereTypeUser()
                            ->latest();

        return new PurchaseOrderCollection($records->paginate(config('tenant.items_per_page')));
    }


    public function tables() {

        $suppliers = $this->table('suppliers');
        // $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        // $currency_types = CurrencyType::whereActive()->get();
        $company = Company::active();
        $payment_method_types = PaymentMethodType::all();

        $currencies = Currency::all();
        $taxes = $this->table('taxes');

        return compact('suppliers', 'establishment','company','currencies','payment_method_types', 'taxes');
    }


    public function item_tables()
    {

        $items = $this->table('items');
        // $affectation_igv_types = AffectationIgvType::whereActive()->get();
        // $system_isc_types = SystemIscType::whereActive()->get();
        // $price_types = PriceType::whereActive()->get();
        // $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        // $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        // $attribute_types = AttributeType::whereActive()->orderByDescription()->get();
        $warehouses = Warehouse::all();

        $taxes = $this->table('taxes');

        return compact('items', 'taxes', 'warehouses');
    }


    public function record($id)
    {
        $record = new PurchaseOrderResource(PurchaseOrder::findOrFail($id));

        return $record;
    }


    public function getFullDescription($row){

        $desc = ($row->internal_id)?$row->internal_id.' - '.$row->name : $row->name;
        $category = ($row->category) ? " - {$row->category->name}" : "";
        $brand = ($row->brand) ? " - {$row->brand->name}" : "";

        $desc = "{$desc} {$category} {$brand}";

        return $desc;
    }


    public function store(PurchaseOrderRequest $request) {

        DB::connection('tenant')->transaction(function () use ($request) {

            $data = $this->mergeData($request);

            $id = $request->input('id');

            $this->purchase_order =  PurchaseOrder::updateOrCreate( ['id' => $id], $data);

            $this->purchase_order->items()->delete();

            foreach ($data['items'] as $row) {
                $this->purchase_order->items()->create($row);
            }
            if(isset($data['taxes'])) {
                $this->purchase_order->taxes = $data['taxes'];
                $this->purchase_order->save();
            }

            $temp_path = $request->input('attached_temp_path');

            if($temp_path) {

                $datenow = date('YmdHis');
                $file_name_old = $request->input('attached');
                $file_name_old_array = explode('.', $file_name_old);
                $file_name = Str::slug($this->purchase_order->id).'-'.$datenow.'.'.$file_name_old_array[1];
                $file_content = file_get_contents($temp_path);
                Storage::disk('tenant')->put('purchase_order_attached'.DIRECTORY_SEPARATOR.$file_name, $file_content);
                $this->purchase_order->upload_filename = $file_name;
                $this->purchase_order->save();

            }

            $this->setFilename();
            $this->createPdf($this->purchase_order, "a4", $this->purchase_order->filename);
            //$this->email($this->purchase_order);
        });

        return [
            'success' => true,
            'data' => [
                'id' => $this->purchase_order->id,
                'number_full' => $this->purchase_order->number_full,
            ],
        ];
    }


    public function mergeData($inputs)
    {

        $this->company = Company::active();

        $values = [
            'user_id' => auth()->id(),
            'supplier' => Person::with('typePerson', 'typeRegime', 'identity_document_type', 'country', 'department', 'city')->findOrFail($inputs['supplier_id']),
            'external_id' => Str::uuid()->toString(),
            'establishment' => EstablishmentInput::set($inputs['establishment_id']),
            'soap_type_id' => $this->company->soap_type_id,
            'state_type_id' => '01'
        ];

        $inputs->merge($values);

        return $inputs->all();
    }



    private function setFilename(){

        $name = [$this->purchase_order->prefix,$this->purchase_order->id,date('Ymd')];
        $this->purchase_order->filename = join('-', $name);
        $this->purchase_order->save();

    }


    public function table($table)
    {
        switch ($table) {            
            
            case 'taxes':

                return Tax::all()->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'name' => $row->name,
                        'code' => $row->code,
                        'rate' =>  $row->rate,
                        'conversion' =>  $row->conversion,
                        'is_percentage' =>  $row->is_percentage,
                        'is_fixed_value' =>  $row->is_fixed_value,
                        'is_retention' =>  $row->is_retention,
                        'in_base' =>  $row->in_base,
                        'in_tax' =>  $row->in_tax,
                        'type_tax_id' =>  $row->type_tax_id,
                        'type_tax' =>  $row->type_tax,
                        'retention' =>  0,
                        'total' =>  0,
                    ];
                });
                break;

            case 'suppliers':

                $suppliers = Person::whereType('suppliers')->orderBy('name')->get()->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'description' => $row->number.' - '.$row->name,
                        'name' => $row->name,
                        'number' => $row->number,
                        'email' => $row->email,
                        'identity_document_type_id' => $row->identity_document_type_id,                        
                        'address' =>  $row->address,
                        'email' =>  $row->email,
                        'telephone' =>  $row->telephone,

                    ];
                });
                return $suppliers;

                break;

            case 'items':

                $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();

                $items = Item::orderBy('name')->whereNotIsSet()
                    ->get()->transform(function($row) use($warehouse){
                    $full_description = $this->getFullDescription($row);
                    return [
                        'id' => $row->id,
                        'name' => $row->name,
                        'description' => $row->description,
                        'full_description' => $full_description,
                        'currency_type_id' => $row->currency_type_id,
                        'currency_type_symbol' => $row->currency_type->symbol,
                        'sale_unit_price' => $row->sale_unit_price,
                        'purchase_unit_price' => $row->purchase_unit_price,
                        'unit_type_id' => $row->unit_type_id,
                        'purchase_tax_id' => $row->purchase_tax_id,
                        'lots_enabled' => (bool) false, //$row->lots_enabled,
                        'has_perception' => (bool) $row->has_perception,
                        'percentage_perception' => $row->percentage_perception,
                        'warehouse_description' => $warehouse->description,
                        'warehouse' => $warehouse,
                        'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                            return [
                                'id' => $row->id,
                                'description' => "{$row->description}",
                                'item_id' => $row->item_id,
                                'unit_type' => $row->unit_type,
                                'unit_type_id' => $row->unit_type_id,
                                'quantity_unit' => $row->quantity_unit,
                                'price1' => $row->price1,
                                'price2' => $row->price2,
                                'price3' => $row->price3,
                                'price_default' => $row->price_default,
                            ];
                        }),
                        'unit_type' => $row->unit_type,
                        'tax' => $row->tax,

                    ];
                });
                return $items;

                break;
            default:
                return [];

                break;
        }
    }


    public function download($external_id, $format = "a4") {

        $purchase_order = PurchaseOrder::with(['currency', 'currency_type'])->where('external_id', $external_id)->first();

        if (!$purchase_order) throw new Exception("El código {$external_id} es inválido, no se encontro la cotización de compra relacionada");

        $this->reloadPDF($purchase_order, $format, $purchase_order->filename);

        return $this->downloadStorage($purchase_order->filename, 'purchase_order');

    }

    public function downloadAttached($external_id) {

        $purchase_order = PurchaseOrder::where('external_id', $external_id)->first();

        if (!$purchase_order) throw new Exception("El código {$external_id} es inválido, no se encontro la orden de compra relacionada");

        return Storage::disk('tenant')->download('purchase_order_attached'.DIRECTORY_SEPARATOR.$purchase_order->upload_filename);
        
    }

    public function toPrint($external_id, $format) {

        $purchase_order = PurchaseOrder::where('external_id', $external_id)->first();

        if (!$purchase_order) throw new Exception("El código {$external_id} es inválido, no se encontro la cotización de compra relacionada");

        $this->reloadPDF($purchase_order, $format, $purchase_order->filename);
        $temp = tempnam(sys_get_temp_dir(), 'purchase_order');

        file_put_contents($temp, $this->getStorage($purchase_order->filename, 'purchase_order'));

        return response()->file($temp);

    }


    private function reloadPDF($purchase_order, $format, $filename) {
        $this->createPdf($purchase_order, $format, $filename);
    }


    public function createPdf($purchase_order = null, $format_pdf = null, $filename = null) {

        $template = new Template();
        $pdf = new Mpdf();

        $document = ($purchase_order != null) ? $purchase_order : $this->purchase_order;
        $company = CoCompany::active();
        $filename = ($filename != null) ? $filename : $this->purchase_order->filename;

        $base_template = config('tenant.pdf_template');

        $html = $template->pdf($base_template, "purchase_order", $company, $document, $format_pdf);

        $pdf_font_regular = config('tenant.pdf_name_regular');
        $pdf_font_bold = config('tenant.pdf_name_bold');

        if ($pdf_font_regular != false) {
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $pdf = new Mpdf([
                'fontDir' => array_merge($fontDirs, [
                    app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                                DIRECTORY_SEPARATOR.'pdf'.
                                                DIRECTORY_SEPARATOR.$base_template.
                                                DIRECTORY_SEPARATOR.'font')
                ]),
                'fontdata' => $fontData + [
                    'custom_bold' => [
                        'R' => $pdf_font_bold.'.ttf',
                    ],
                    'custom_regular' => [
                        'R' => $pdf_font_regular.'.ttf',
                    ],
                ]
            ]);
        }

        $path_css = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.
                                             DIRECTORY_SEPARATOR.'pdf'.
                                             DIRECTORY_SEPARATOR.$base_template.
                                             DIRECTORY_SEPARATOR.'style.css');

        $stylesheet = file_get_contents($path_css);

        $pdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        $pdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        if ($format_pdf != 'ticket') {
            if(config('tenant.pdf_template_footer')) {
                $html_footer = $template->pdfFooter($base_template);
                $pdf->SetHTMLFooter($html_footer);
            }
        }

        $this->uploadFile($filename, $pdf->output('', 'S'), 'purchase_order');
    }


    public function uploadFile($filename, $file_content, $file_type) {
        $this->uploadStorage($filename, $file_content, $file_type);
    }


    // public function email($purchase_order)
    // {
    //     $suppliers = $purchase_order->suppliers;
    //     // dd($suppliers);

    //     foreach ($suppliers as $supplier) {

    //         $client = Person::find($supplier->supplier_id);
    //         $supplier_email = $supplier->email;

    //         Mail::to($supplier_email)->send(new PurchaseOrderEmail($client, $purchase_order));
    //     }

    //     return [
    //         'success' => true
    //     ];
    // }

    public function uploadAttached(Request $request)
    {
        if ($request->hasFile('file')) {
            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
            ];

            return $this->upload_attached($new_request);
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

    function upload_attached($request)
    {
        $file = $request['file'];
        $type = $request['type'];

        $temp = tempnam(sys_get_temp_dir(), $type);
        file_put_contents($temp, file_get_contents($file));

        $mime = mime_content_type($temp);
        $data = file_get_contents($temp);

        return [
            'success' => true,
            'data' => [
                'filename' => $file->getClientOriginalName(),
                'temp_path' => $temp,
                'temp_image' => 'data:' . $mime . ';base64,' . base64_encode($data)
            ]
        ];
    }

    public function anular($id)
    {
        $obj =  PurchaseOrder::find($id);
        $obj->state_type_id = 11;
        $obj->save();
        return [
            'success' => true,
            'message' => 'Orden de compra anulada con éxito'
        ];
    }
    
    public function sendEmailApi(Request $request)
    {
        $purchase_order = PurchaseOrder::findOrFail($request->id);
        $pdf_content = $this->getStorage($purchase_order->filename, 'purchase_order');
        $base64_pdf = base64_encode($pdf_content);

        $payload = [
            "email" => $request->email_cc,
            "subject" => "Orden de Compra N° {$purchase_order->id}",
            "message" => "Adjunto encontrará la orden de compra.",
            "document_base64" => $base64_pdf,
            "filename" => $purchase_order->filename,
            "document_type" => "pdf"
        ];

        $company = ServiceTenantCompany::firstOrFail();
        $token = $company->api_token;
        $base_url = rtrim(config('tenant.service_fact'), '/');
        $endpoint = '/ubl2.1/send-email/external';
        $url = $base_url . $endpoint;

        try {
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json",
                "Accept: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $response_data = json_decode($response, true);

            if ($http_code >= 200 && $http_code < 300) {
                return [
                    'success' => true,
                    'message' => 'Correo enviado correctamente',
                    'response' => $response_data
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error en la respuesta de la API',
                    'response' => $response_data
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al enviar el correo: ' . $e->getMessage()
            ];
        }
    }

}
