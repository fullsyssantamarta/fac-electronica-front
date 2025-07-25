<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Catalogs\SystemIscType;
use Illuminate\Support\Facades\DB;
use Modules\Factcolombia1\Models\Tenant\TypeUnit;

class DocumentItem extends ModelTenant
{
    protected $appends = ['from_remission'];
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'item_id',
        'item',
        'quantity',
        // 'unit_value',

        // 'affectation_igv_type_id',
        // 'total_base_igv',
        // 'percentage_igv',
        // 'total_igv',

        // 'system_isc_type_id',
        // 'total_base_isc',
        // 'percentage_isc',
        // 'total_isc',

        // 'total_base_other_taxes',
        // 'percentage_other_taxes',
        // 'total_other_taxes',
        // 'total_taxes',

        // 'price_type_id',
        'unit_price',

        // 'total_value',
        // 'total_charge',
        // 'total_discount',
        'total',

        // 'attributes',
        // 'charges',
        // 'discounts',
        'total_plastic_bag_taxes',
        'warehouse_id',
        // 'name_product_pdf',
        // 'additional_information',

        //co
        'unit_type_id',
        'tax_id',
        'tax',
        'total_tax',
        'subtotal',
        'discount'
    ];


    protected $casts = [
        'tax' => 'object'
    ];


    public function unit_type()
    {
        return $this->belongsTo(TypeUnit::class, 'unit_type_id');
    }

    public function getItemAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setItemAttribute($value)
    {
        $this->attributes['item'] = (is_null($value))?null:json_encode($value);
    }


    // public function affectation_igv_type()
    // {
    //     return $this->belongsTo(AffectationIgvType::class, 'affectation_igv_type_id');
    // }

    // public function system_isc_type()
    // {
    //     return $this->belongsTo(SystemIscType::class, 'system_isc_type_id');
    // }

    // public function price_type()
    // {
    //     return $this->belongsTo(PriceType::class, 'price_type_id');
    // }

    public function m_item()
    {
        return $this->belongsTo(Item::class,'item_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function relation_item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }



    public function scopeWhereDefaultDocumentType($query, $params)
    {

        $db_raw = DB::raw("document_items.id as id, documents.series as series, documents.number as number,
                            document_items.item as item, document_items.quantity as quantity,
                            documents.date_of_issue as date_of_issue");

        if($params['person_id']){

            return $query->whereHas('document', function($q) use($params){
                            $q->whereBetween($params['date_range_type_id'], [$params['date_start'], $params['date_end']])
                                ->where('customer_id', $params['person_id'])
                                ->whereTypeUser();
                        })
                        ->join('documents', 'document_items.document_id', '=', 'documents.id')
                        ->select($db_raw)
                        ->latest('id');

        }


        return $query->whereHas('document', function($q) use($params){
                    $q->whereBetween($params['date_range_type_id'], [$params['date_start'], $params['date_end']])
                        ->where('user_id', $params['seller_id'])
                        ->whereTypeUser();
                })
                ->join('documents', 'document_items.document_id', '=', 'documents.id')
                ->select($db_raw)
                ->latest('id');

    }


    /**
     * Valor neto sin impuestos
     *
     * @return float
     */
    public function getNetValueAttribute()
    {
        return $this->generalApplyNumberFormat($this->quantity * $this->unit_price);
    }


    /**
     *
     * Datos para pdf reporte articulos vendidos
     *
     * @return array
     */
    public function getDataReportSoldItems()
    {
        $cost = $this->generalApplyNumberFormat($this->relation_item->purchase_unit_price * $this->quantity);

        $type_name = 'Factura';
        $factor = 1;
        $reference_number = null;

        if (
            isset($this->document) &&
            $this->document->type_document_id == '3'
        ) {
            if (isset($this->document->note_concept_id) && $this->document->note_concept_id == 5) {
                $type_name = 'Nota de Crédito (Anulación)';
                $factor = -1;

                // Buscar la factura referenciada
                if ($this->document->reference_id) {
                    $referenced = Document::find($this->document->reference_id);
                    if ($referenced) {
                        $reference_number = $referenced->prefix . '-' . $referenced->number;
                    }
                }
            } else {
                $type_name = 'Nota de Crédito';
                $factor = -1;
            }
        }

        return [
            'type_name' => $type_name,
            'internal_id' => $this->item->internal_id,
            'name' => $this->item->name,
            'quantity' => (float) $this->quantity * $factor,
            'cost' => $cost * $factor,
            'net_value' => $this->net_value * $factor,
            'discount' => $this->discount * $factor,
            'utility' => $this->generalApplyNumberFormat(($this->net_value - $cost) * $factor),
            'total_tax' => $this->total_tax * $factor,
            'total' => $this->total * $factor,
            'reference_number' => $reference_number,
        ];
    }


    /**
     *
     * Filtros para reporte articulos vendidos
     *
     * @param  Builder $query
     * @param  Request $request
     * @return Builder
     */
    public function scopeFilterReportSoldItems($query, $request)
    {
        $brand_id = $request->brand_id ?? null;
        $item_id = $request->item_id ?? null;

        return $query->with([
                    'document' => function($query){
                        return $query->select([
                            'id',
                            'prefix',
                            'number',
                            'type_document_id',
                            'note_concept_id',
                            'reference_id' // <-- Agrega este campo
                        ]);
                    },
                    'relation_item' => function($query){
                        return $query->select([
                            'id',
                            'purchase_unit_price'
                        ]);
                    },
                ])
                ->filterSoldItemsDocument($request)
                ->filterByItem($item_id)
                ->filterByBrand($brand_id)
                ->latest('id');
    }


    /**
     *
     * Filtrar por producto
     *
     * @param  Builder $query
     * @param  int $item_id
     * @return Builder
     */
    public function scopeFilterByItem($query, $item_id)
    {
        if($item_id) $query->where('item_id', $item_id);

        return $query;
    }


    /**
     *
     * Filtrar por marca
     *
     * @param  Builder $query
     * @param  int $brand_id
     * @return Builder
     */
    public function scopeFilterByBrand($query, $brand_id)
    {
        if($brand_id)
        {
            $query->whereHas('relation_item', function($query) use($brand_id){
                return $query->where('brand_id', $brand_id);
            });
        }

        return $query;
    }


    /**
     *
     * Filtros para reporte articulos vendidos en el documento
     *
     * @param  Builder $query
     * @param  Request $request
     * @return Builder
     */
    public function scopeFilterSoldItemsDocument($query, $request)
    {
        $customer_id = $request->customer_id ?? null;
        $user_id = $request->user_id ?? null;
        $start_date = $request->start_date ?? null;
        $end_date = $request->end_date ?? null;
        $start_time = $request->start_time ?? null;
        $end_time = $request->end_time ?? null;
        $establishment_id = $request->establishment_id ?? null;

        return $query->whereHas('document', function($document) use ($customer_id, $user_id, $start_date, $end_date, $start_time, $end_time, $establishment_id){

            return $document->filterByCustomer($customer_id)
                            ->filterByUser($user_id)
                            ->filterByRangeTimeOfIssue($start_time, $end_time)
                            ->filterByRangeDateOfIssue($start_date, $end_date)
                            ->filterInvoiceDocument()
                            ->filterByEstablishment($establishment_id);
        });
    }

    public function getFromRemissionAttribute()
    {
        return !is_null($this->document) && !is_null($this->document->remission_id);
    }

}