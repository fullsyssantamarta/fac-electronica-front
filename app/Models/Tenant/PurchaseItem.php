<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\PriceType;
use App\Models\Tenant\Catalogs\SystemIscType;
use Modules\Item\Models\ItemLot;
use Modules\Inventory\Models\Warehouse;
use Modules\Factcolombia1\Models\Tenant\TypeUnit;
use Modules\Factcolombia1\Models\Tenant\Tax;

class PurchaseItem extends ModelTenant
{
    protected $with = ['lots', 'warehouse'];
    public $timestamps = false;

    protected $fillable = [
        'purchase_id',
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
        'total',

        // 'attributes',
        // 'charges',
        'lot_code',
        'warehouse_id',
        'notes',
        'unit_type_id',
        'tax_id',
        'tax',
        'total_tax',
        'subtotal',
        'discount',
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

    public function getAttributesAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setAttributesAttribute($value)
    {
        $this->attributes['attributes'] = (is_null($value))?null:json_encode($value);
    }

    public function getChargesAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setChargesAttribute($value)
    {
        $this->attributes['charges'] = (is_null($value))?null:json_encode($value);
    }

    public function getDiscountsAttribute($value)
    {
        return (is_null($value))?null:(object) json_decode($value);
    }

    public function setDiscountsAttribute($value)
    {
        $this->attributes['discounts'] = (is_null($value))?null:json_encode($value);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    
    public function lots()
    {
        return $this->morphMany(ItemLot::class, 'item_loteable');
    }

    
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    
    public function relation_item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}