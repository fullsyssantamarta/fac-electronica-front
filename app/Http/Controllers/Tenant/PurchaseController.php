<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Person;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\ChargeDiscountType;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\PurchaseItem;
use Modules\Purchase\Models\PurchaseOrder;

use App\CoreFacturalo\Requests\Inputs\Common\LegendInput;
use App\Models\Tenant\Item;
use App\Http\Resources\Tenant\PurchaseCollection;
use App\Http\Resources\Tenant\PurchaseResource;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\DocumentType;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Catalogs\SystemIscType;
use App\Models\Tenant\Catalogs\AttributeType;
use App\Models\Tenant\Company;
use App\Http\Requests\Tenant\PurchaseRequest;
use App\Http\Requests\Tenant\PurchaseImportRequest;

use Illuminate\Support\Str;
use App\CoreFacturalo\Requests\Inputs\Common\PersonInput;
use App\Models\Tenant\PaymentMethodType;
use Carbon\Carbon;
use Modules\Inventory\Models\Warehouse;
use App\Models\Tenant\InventoryKardex;
use App\Models\Tenant\ItemWarehouse;
use Modules\Finance\Traits\FinanceTrait;
use Modules\Item\Models\ItemLotsGroup;
use Modules\Factcolombia1\Models\Tenant\{
    Currency,
    Tax,
};
use Barryvdh\DomPDF\Facade as PDF;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalPrefix;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\ChartAccountSaleConfiguration;
use Modules\Accounting\Models\AccountingChartAccountConfiguration;
use Modules\Accounting\Helpers\AccountBalanceHelper;
use Modules\Accounting\Helpers\AccountingEntryHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PurchaseController extends Controller
{

    use FinanceTrait;

    public function index()
    {
        return view('tenant.purchases.index');
    }


    public function create($purchase_order_id = null)
    {
        return view('tenant.purchases.form', compact('purchase_order_id'));
    }

    public function columns()
    {
        return [
            'number' => 'Número',
            'date_of_issue' => 'Fecha de emisión',
            'date_of_due' => 'Fecha de vencimiento',
            'date_of_payment' => 'Fecha de pago',
            'name' => 'Nombre proveedor',
        ];
    }

    public function records(Request $request)
    {

        $records = $this->getRecords($request);

        return new PurchaseCollection($records->paginate(config('tenant.items_per_page')));
    }

    public function getRecords($request){

        switch ($request->column) {
            case 'name':

                $records = Purchase::whereHas('supplier', function($query) use($request){
                                return $query->where($request->column, 'like', "%{$request->value}%");
                            })
                            ->whereTypeUser()
                            ->latest();
                break;

            case 'date_of_issue':
                if (strlen($request->value) == 7) {
                    // Si el valor es un mes (YYYY-MM), filtrar por todo el mes
                    $year_month = explode('-', $request->value);
                    $year = $year_month[0];
                    $month = $year_month[1];

                    $records = Purchase::whereYear('date_of_issue', $year)
                                     ->whereMonth('date_of_issue', $month)
                                     ->whereTypeUser()
                                     ->latest();
                } else {
                    // Si es una fecha específica (YYYY-MM-DD)
                    $records = Purchase::whereDate('date_of_issue', $request->value)
                                     ->whereTypeUser()
                                     ->latest();
                }
                break;

            case 'date_of_payment':

                $records = Purchase::whereHas('purchase_payments', function($query) use($request){
                                return $query->where($request->column, 'like', "%{$request->value}%");
                            })
                            ->whereTypeUser()
                            ->latest();

                break;

            default:

                $records = Purchase::where($request->column, 'like', "%{$request->value}%")
                            ->whereTypeUser()
                            ->latest();

                break;
        }

        return $records;

    }

    public function tables()
    {
        $suppliers = $this->table('suppliers');
        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        // $currency_types = CurrencyType::whereActive()->get();
        $document_types_invoice = DocumentType::whereIn('id', ['01', 'GU75', 'NE76'])->get();
        $document_types_notes = DocumentType::whereIn('id', ['07', '08'])->get();
        // $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        // $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        $company = Company::active();
        $payment_method_types = PaymentMethodType::all();
        $payment_destinations = $this->getPaymentDestinations();
        $customers = $this->getPersons('customers');

        $currencies = Currency::all();
        $taxes = $this->table('taxes');

        return compact('suppliers', 'establishment','currencies',
                    'taxes', 'document_types_invoice','company','payment_method_types', 'payment_destinations', 'customers', 'document_types_notes');
    }

    public function item_tables()
    {
        ini_set('memory_limit', '-1');
        $items = $this->table('items');
        $taxes = $this->table('taxes');
        $categories = [];
        // $affectation_igv_types = AffectationIgvType::whereActive()->get();
        // $system_isc_types = SystemIscType::whereActive()->get();
        // $price_types = PriceType::whereActive()->get();
        // $discount_types = ChargeDiscountType::whereType('discount')->whereLevel('item')->get();
        // $charge_types = ChargeDiscountType::whereType('charge')->whereLevel('item')->get();
        // $attribute_types = AttributeType::whereActive()->orderByDescription()->get();
        $warehouses = Warehouse::all();
        return compact('items', 'categories', 'taxes','warehouses');
    }

    public function record($id)
    {

        $record = new PurchaseResource(Purchase::findOrFail($id));

        return $record;
    }

    public function edit($id)
    {
        $resourceId = $id;
        return view('tenant.purchases.form_edit', compact('resourceId'));
    }

    public function note($id)
    {
        $resourceId = $id;
        return view('tenant.purchases.note', compact('resourceId'));
    }

    public function store(PurchaseRequest $request) {
        $data = self::convert($request);

        try {
            $purchase = DB::connection('tenant')->transaction(function () use ($data) {
                $doc = Purchase::create($data);
                foreach ($data['items'] as $row) {
                    $p_item = new PurchaseItem;
                    $p_item->fill($row);
                    $p_item->purchase_id = $doc->id;
                    $p_item->save();

                    $item = Item::find($row['item_id']);
                    if($item) {
                        $item->purchase_unit_price = $row['unit_price'];
                        if(isset($row['sale_unit_price'])) {
                            $item->sale_unit_price = $row['sale_unit_price'];
                        }
                        $item->save();
                    }

                    if(array_key_exists('lots', $row)){
                        foreach ($row['lots'] as $lot){
                            $p_item->lots()->create([
                                'date' => $lot['date'],
                                'series' => $lot['series'],
                                'item_id' => $row['item_id'],
                                'warehouse_id' => $row['warehouse_id'],
                                'has_sale' => false,
                                'state' => $lot['state']
                            ]);
                        }
                    }

                    if(array_key_exists('item', $row))
                    {
                        if( $row['item']['lots_enabled'] == true)
                        {
                            ItemLotsGroup::create([
                                'code'  => $row['lot_code'],
                                'quantity'  => $row['quantity'],
                                'date_of_due'  => $row['date_of_due'],
                                'item_id' => $row['item_id']
                            ]);
                        }
                    }
                }

                foreach ($data['payments'] as $payment) {
                    $record_payment = $doc->purchase_payments()->create($payment);
                    if(isset($payment['payment_destination_id'])){
                        $this->createGlobalPayment($record_payment, $payment);
                    }
                }

                // Registrar asientos contables compra/debito
                if($doc->document_type_id == '01' || $doc->document_type_id == '09') {
                    $this->registerAccountingPurchaseEntries($doc);
                }

                // Registrar asientos contables credito
                if($doc->document_type_id == '07') {
                    $this->registerAccountingCreditNotePurchase($doc);
                }

                return $doc;
            });

            return [
                'success' => true,
                'data' => [
                    'id' => $purchase->id,
                    'number_full' => "{$purchase->series}-{$purchase->number}",
                ],
            ];
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return [
                'success' => false,
                'message' => 'No se pudo registrar la compra: '.$e->getMessage(),
            ];
        }
    }

    private function registerAccountingPurchaseEntries($document)
    {
        $accountConfiguration = AccountingChartAccountConfiguration::first();
        if(!$accountConfiguration) return;
        $accountIdInventory = ChartOfAccount::where('code',$accountConfiguration->inventory_account)->first();
        $accountIdLiability = ChartOfAccount::where('code',$accountConfiguration->supplier_payable_account)->first();
        $document_type = DocumentType::find($document->document_type_id);

        AccountingEntryHelper::registerEntry([
            'prefix_id' => 2,
            'description' => $document_type->description . ' #' . $document->series . '-' . $document->number,
            'purchase_id' => $document->id,
            'movements' => [
                [
                    'account_id' => $accountIdInventory->id,
                    'debit' => $document->sale,
                    'credit' => 0,
                    'affects_balance' => true,
                ],
                [
                    'account_id' => $accountIdLiability->id,
                    'debit' => 0,
                    'credit' => $document->total,
                    'affects_balance' => true,
                ],
            ],
            'taxes' => $document->taxes ?? [],
            'tax_config' => [
                'tax_field' => 'chart_account_purchase',
                'tax_debit' => true,
                'tax_credit' => false,
                'retention_debit' => false,
                'retention_credit' => true,
            ],
        ]);
    }

    private function registerAccountingCreditNotePurchase($document)
    {
        $accountConfiguration = AccountingChartAccountConfiguration::first();
        $accountIdInventory = ChartOfAccount::where('code',$accountConfiguration->inventory_account)->first();
        $accountIdLiability = ChartOfAccount::where('code',$accountConfiguration->supplier_payable_account)->first();
        $document_type = DocumentType::find($document->document_type_id);

        AccountingEntryHelper::registerEntry([
            'prefix_id' => 2,
            'description' => $document_type->description . ' #' . $document->series . '-' . $document->number,
            'purchase_id' => $document->id,
            'movements' => [
                [
                    'account_id' => $accountIdInventory->id,
                    'debit' => $document->sale,
                    'credit' => 0,
                    'affects_balance' => true,
                ],
                [
                    'account_id' => $accountIdLiability->id,
                    'debit' => 0,
                    'credit' => $document->total,
                    'affects_balance' => true,
                ],
            ],
            'taxes' => $document->taxes ?? [],
            'tax_config' => [
                'tax_field' => 'chart_account_return_purchase',
                'tax_debit' => false,
                'tax_credit' => true,
                'retention_debit' => true,
                'retention_credit' => false,
            ],
        ]);
    }

    public function update(PurchaseRequest $request)
    {
        $purchase = DB::connection('tenant')->transaction(function () use ($request) {

            $doc = Purchase::firstOrNew(['id' => $request['id']]);
           // return json_encode($doc);
            $doc->fill($request->all());
            $doc->save();

            $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
            //proceso para eliminar los actualizar el stock de proiductos
            foreach ($doc->items as $item) {
                $item->purchase->inventory_kardex()->create([
                    'date_of_issue' => date('Y-m-d'),
                    'item_id' => $item->item_id,
                    'warehouse_id' => $establishment->id,
                    'quantity' => -$item->quantity,
                ]);
                $wr = ItemWarehouse::where([['item_id', $item->item_id],['warehouse_id', $establishment->id]])->first();
                $wr->stock =  $wr->stock - $item->quantity;
                $wr->save();
            }

            foreach ($doc->items()->get() as $it) {
                // dd($it);
                $it->lots()->delete();
            }

            $doc->items()->delete();

            foreach ($request['items'] as $row)
            {
                // $doc->items()->create($row);
                $p_item = new PurchaseItem;
                $p_item->fill($row);
                $p_item->purchase_id = $doc->id;
                $p_item->save();

                if(isset($row['sale_unit_price']) && $row['sale_unit_price'] > 0) {
                    $item = Item::find($row['item_id']);
                    if($item) {
                        $item->sale_unit_price = $row['sale_unit_price'];
                        $item->save();
                    }
                }

                if(array_key_exists('lots', $row)){

                    foreach ($row['lots'] as $lot){

                        $p_item->lots()->create([
                            'date' => $lot['date'],
                            'series' => $lot['series'],
                            'item_id' => $row['item_id'],
                            'warehouse_id' => $row['warehouse_id'],
                            'has_sale' => false
                        ]);

                    }
                }
            }

            // $doc->purchase_payments()->delete();
            $this->deleteAllPayments($doc->purchase_payments);

            foreach ($request['payments'] as $payment) {

                $record_payment = $doc->purchase_payments()->create($payment);

                if(isset($payment['payment_destination_id'])){
                    $this->createGlobalPayment($record_payment, $payment);
                }

                if(isset($payment['payment_filename'])){
                    $record_payment->payment_file()->create([
                        'filename' => $payment['payment_filename']
                    ]);
                }
            }

            return $doc;
        });

        return [
            'success' => true,
            'data' => [
                'id' => $purchase->id,
            ],
        ];
    }


    public function anular($id)
    {
        $obj =  Purchase::find($id);
        $obj->state_type_id = 11;
        $obj->save();

        $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
        $warehouse = Warehouse::where('establishment_id',$establishment->id)->first();

        //proceso para eliminar los actualizar el stock de proiductos
        foreach ($obj->items as $item) {
            $item->purchase->inventory_kardex()->create([
                'date_of_issue' => date('Y-m-d'),
                'item_id' => $item->item_id,
                'warehouse_id' => $establishment->id,
                'quantity' => -$item->quantity,
            ]);
            $wr = ItemWarehouse::where([['item_id', $item->item_id],['warehouse_id', $warehouse->id]])->first();
            $wr->stock =  $wr->stock - $item->quantity;
            $wr->save();
        }

        return [
            'success' => true,
            'message' => 'Compra anulada con éxito'
        ];
    }

    public static function convert($inputs)
    {
        $company = Company::active();
        $values = [
            'user_id' => auth()->id(),
            'external_id' => Str::uuid()->toString(),
            'supplier' => Person::with('typePerson', 'typeRegime', 'identity_document_type', 'country', 'department', 'city')->findOrFail($inputs['supplier_id']),
            'soap_type_id' => $company->soap_type_id,
            'group_id' => ($inputs->document_type_id === '01') ? '01':'02',
            'state_type_id' => '01'
        ];

        $inputs->merge($values);

        return $inputs->all();
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
                        'perception_agent' => (bool) $row->perception_agent,
                        'identity_document_type_id' => $row->identity_document_type_id,
                        'address' =>  $row->address,
                        'email' =>  $row->email,
                        'telephone' =>  $row->telephone,
                    ];
                });
                return $suppliers;

                break;

            case 'items':
                // Modificar para retornar solo los primeros 20 items
                $items = Item::whereNotIsSet()
                            ->whereIsActive()
                            ->orderBy('name')
                            ->take(20)
                            ->get();

                return collect($items)->transform(function($row) {
                    $full_description = ($row->internal_id)?$row->internal_id.' - '.$row->name:$row->name;
                    // Obtener el stock del almacén del usuario actual
                    $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
                    $stock = 0;
                    if($establishment) {
                        $warehouse = ItemWarehouse::where('item_id', $row->id)
                                                ->where('warehouse_id', $establishment->id)
                                                ->first();
                        $stock = $warehouse ? $warehouse->stock : 0;
                    }

                    return [
                        'id' => $row->id,
                        'item_code'  => $row->item_code,
                        'name'  => $row->name,
                        'description'  => $row->description,
                        'full_description' => $full_description,
                        'currency_type_id' => $row->currency_type_id,
                        'currency_type_symbol' => $row->currency_type->symbol,
                        'sale_unit_price' => $row->sale_unit_price,
                        'purchase_unit_price' => $row->purchase_unit_price,
                        'unit_type_id' => $row->unit_type_id,
                        'purchase_tax_id' => $row->purchase_tax_id,
                        'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                        'has_perception' => (bool) $row->has_perception,
                        'lots_enabled' => (bool) $row->lots_enabled,
                        'percentage_perception' => $row->percentage_perception,
                        'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                            return [
                                'id' => $row->id,
                                'description' => "{$row->description}",
                                'item_id' => $row->item_id,
                                'unit_type_id' => $row->unit_type_id,
                                'unit_type' => $row->unit_type,
                                'quantity_unit' => $row->quantity_unit,
                                'price1' => $row->price1,
                                'price2' => $row->price2,
                                'price3' => $row->price3,
                                'price_default' => $row->price_default,
                            ];
                        }),
                        'series_enabled' => (bool) $row->series_enabled,
                        'unit_type' => $row->unit_type,
                        'tax' => $row->tax,

                        // 'warehouses' => collect($row->warehouses)->transform(function($row) {
                        //     return [
                        //         'warehouse_id' => $row->warehouse->id,
                        //         'warehouse_description' => $row->warehouse->description,
                        //         'stock' => $row->stock,
                        //     ];
                        // })
                        'stock' => $stock,
                    ];
                });
//                return $items;

                break;
            default:

                return [];

                break;
        }
    }

    // Agregar nuevo método para búsqueda de items
    public function searchItems(Request $request)
    {
        $search = $request->input('search');
        $newItemId = $request->input('new_item_id');

        $query = Item::whereNotIsSet()
                    ->whereIsActive();

        // Si hay un nuevo item, asegurarse de incluirlo
        if ($newItemId) {
            $query->where(function($q) use($search, $newItemId) {
                $q->where('id', $newItemId)
                  ->orWhere(function($sq) use($search) {
                      if ($search) {
                          $sq->where('name', 'like', "%{$search}%")
                             ->orWhere('internal_id', 'like', "%{$search}%")
                             ->orWhere('description', 'like', "%{$search}%");
                      }
                  });
            });
        } else if ($search) {
            $query->where(function($q) use($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('internal_id', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $items = $query->orderByDesc('id') // Ordenar por ID descendente para que el nuevo aparezca primero
                       ->take(20)
                       ->get();

        return collect($items)->transform(function($row) {
            $full_description = ($row->internal_id) ? $row->internal_id.' - '.$row->name : $row->name;

            $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
            $stock = 0;
            if($establishment) {
                $warehouse = ItemWarehouse::where('item_id', $row->id)
                                        ->where('warehouse_id', $establishment->id)
                                        ->first();
                $stock = $warehouse ? $warehouse->stock : 0;
            }

            return [
                'id' => $row->id,
                'item_code'  => $row->item_code,
                'name'  => $row->name,
                'description'  => $row->description,
                'full_description' => $full_description,
                'currency_type_id' => $row->currency_type_id,
                'currency_type_symbol' => $row->currency_type->symbol,
                'sale_unit_price' => $row->sale_unit_price,
                'purchase_unit_price' => $row->purchase_unit_price,
                'unit_type_id' => $row->unit_type_id,
                'purchase_tax_id' => $row->purchase_tax_id,
                'purchase_affectation_igv_type_id' => $row->purchase_affectation_igv_type_id,
                'has_perception' => (bool) $row->has_perception,
                'lots_enabled' => (bool) $row->lots_enabled,
                'percentage_perception' => $row->percentage_perception,
                'item_unit_types' => collect($row->item_unit_types)->transform(function($row) {
                    return [
                        'id' => $row->id,
                        'description' => "{$row->description}",
                        'item_id' => $row->item_id,
                        'unit_type_id' => $row->unit_type_id,
                        'unit_type' => $row->unit_type,
                        'quantity_unit' => $row->quantity_unit,
                        'price1' => $row->price1,
                        'price2' => $row->price2,
                        'price3' => $row->price3,
                        'price_default' => $row->price_default,
                    ];
                }),
                'series_enabled' => (bool) $row->series_enabled,
                'unit_type' => $row->unit_type,
                'tax' => $row->tax,
                'stock' => $stock,
            };
        });
    }

    public function delete($id)
    {

        try {

            DB::connection('tenant')->transaction(function () use ($id) {

                $row = Purchase::findOrFail($id);
                $this->deleteAllPayments($row->purchase_payments);

                // eliminar asientos contables
                $journal = JournalEntry::where('purchase_id', $row->id)->first();
                $journal->details()->delete();
                $journal->delete();

                $row->delete();

            });

            return [
                'success' => true,
                'message' => 'Compra eliminada con éxito'
            ];

        } catch (Exception $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }



    public function xml2array ( $xmlObject, $out = array () )
    {
        foreach ((array) $xmlObject as $index => $node) {
            $out[$index] = ( is_object ( $node ) ) ?  $this->xml2array($node) : $node;
        }
        return $out;
    }

    function XMLtoArray($xml) {
        $previous_value = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->loadXml($xml);
        libxml_use_internal_errors($previous_value);
        if (libxml_get_errors()) {
            return [];
        }
        return $this->DOMtoArray($dom);
    }

    public function DOMtoArray($root) {
        $result = array();

        if ($root->hasAttributes()) {
            $attrs = $root->attributes;
            foreach ($attrs as $attr) {
                $result['@attributes'][$attr->name] = $attr->value;
            }
        }

        if ($root->hasChildNodes()) {
            $children = $root->childNodes;
            if ($children->length == 1) {
                $child = $children->item(0);
                if (in_array($child->nodeType,[XML_TEXT_NODE,XML_CDATA_SECTION_NODE])) {
                    $result['_value'] = $child->nodeValue;
                    return count($result) == 1
                        ? $result['_value']
                        : $result;
                }

            }
            $groups = array();
            foreach ($children as $child) {
                if (!isset($result[$child->nodeName])) {
                    $result[$child->nodeName] = $this->DOMtoArray($child);
                } else {
                    if (!isset($groups[$child->nodeName])) {
                        $result[$child->nodeName] = array($result[$child->nodeName]);
                        $groups[$child->nodeName] = 1;
                    }
                    $result[$child->nodeName][] = $this->DOMtoArray($child);
                }
            }
        }
        return $result;
    }

    public function import(PurchaseImportRequest $request)
    {
        try
        {
            $model = $request->all();
            $supplier =  Person::whereType('suppliers')->where('number', $model['supplier_ruc'])->first();
            if(!$supplier)
            {
                return [
                    'success' => false,
                    'data' => 'Supplier not exist.'
                ];
            }
            $model['supplier_id'] = $supplier->id;
            $company = Company::active();
            $values = [
                'user_id' => auth()->id(),
                'external_id' => Str::uuid()->toString(),
                'supplier' => PersonInput::set($model['supplier_id']),
                'soap_type_id' => $company['soap_type_id'],
                'group_id' => ($model['document_type_id'] === '01') ? '01':'02',
                'state_type_id' => '01'
            ];

            $data = array_merge($model, $values);

            $purchase = DB::connection('tenant')->transaction(function () use ($data) {
                $doc = Purchase::create($data);
                foreach ($data['items'] as $row)
                {
                    $doc->items()->create($row);
                }

                $doc->purchase_payments()->create([
                    'date_of_payment' => $data['date_of_issue'],
                    'payment_method_type_id' => $data['payment_method_type_id'],
                    'payment' => $data['total'],
                ]);

                return $doc;
            });

            return [
                'success' => true,
                'message' => 'Xml cargado correctamente.',
                'data' => [
                    'id' => $purchase->id,
                ],
            ];



        }catch(Exception $e)
        {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

    }


    public function getPersons($type){

        $persons = Person::whereType($type)->orderBy('name')->take(20)->get()->transform(function($row) {
            return [
                'id' => $row->id,
                'description' => $row->number.' - '.$row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
            ];
        });

        return $persons;

    }

    public function pdf($id)
    {
        $document = $this->record($id);
        $document['establishment'] = Establishment::where('id', $document->establishment_id)->first();
        // dd($document);
        $company = Company::active();

        $pdf = PDF::loadView('tenant.purchases.pdf', compact("document", "company"));
        $filename = 'COMPRA_'.$document->series.$document->number;

        return $pdf->stream($filename.'.pdf');
    }

    public function actaExcel($id)
    {
        $purchase = Purchase::with(['items', 'supplier'])->findOrFail($id);

        // Datos generales
        $fecha = $purchase->date_of_issue;
        $numero_acta = 'ACTA-' . $purchase->id;
        $recibido_por = auth()->user()->name;
        $entregado_por = $purchase->supplier->name;

        // Crear Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->setCellValue('A1', 'ACTA DE RECEPCIÓN DE EQUIPOS');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        // Información general
        $sheet->setCellValue('A3', 'Fecha:');
        $sheet->setCellValue('B3', $fecha);
        $sheet->setCellValue('D3', 'Número de Acta:');
        $sheet->setCellValue('E3', $numero_acta);

        $sheet->setCellValue('A4', 'Recibido por:');
        $sheet->setCellValue('B4', $recibido_por);
        $sheet->setCellValue('D4', 'Entregado por:');
        $sheet->setCellValue('E4', $entregado_por);

        // Tabla de items
        $sheet->setCellValue('A6', '#');
        $sheet->setCellValue('B6', 'Descripción');
        $sheet->setCellValue('C6', 'Cantidad');
        $sheet->setCellValue('D6', 'Estado');
        $sheet->setCellValue('E6', 'Observaciones');
        $sheet->getStyle('A6:E6')->getFont()->setBold(true);

        $rowNum = 7;
        foreach ($purchase->items as $idx => $item) {
            $sheet->setCellValue('A'.$rowNum, $idx + 1);
            $sheet->setCellValue('B'.$rowNum, $item->description ?? $item->item->description ?? '');
            $sheet->setCellValue('C'.$rowNum, $item->quantity);
            $sheet->setCellValue('D'.$rowNum, 'Bueno'); // Puedes cambiar el estado si tienes ese dato
            $sheet->setCellValue('E'.$rowNum, ''); // Observaciones vacías
            $rowNum++;
        }

        // Firmas
        $sheet->setCellValue('B'.($rowNum+2), '_________________________');
        $sheet->setCellValue('B'.($rowNum+3), 'Entregado por');
        $sheet->setCellValue('D'.($rowNum+2), '_________________________');
        $sheet->setCellValue('D'.($rowNum+3), 'Recibido por');

        // Descargar archivo
        $filename = 'acta_recepcion_'.$purchase->id.'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }


}
