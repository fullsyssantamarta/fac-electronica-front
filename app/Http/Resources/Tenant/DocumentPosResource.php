<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Tenant\DocumentPos;
use Modules\Factcolombia1\Models\Tenant\CustomerPurchaseCoupon;

class DocumentPosResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        $sale_note = DocumentPos::find($this->id);
        $sale_note->payments = self::getTransformPayments($sale_note->payments);
        $has_purchase_coupon = CustomerPurchaseCoupon::where('document_id', $this->id)->where('status', 1)->exists();

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'number' => $this->number_full,
            'identifier' => $this->identifier,
            'date_of_issue' => $this->date_of_issue->format('Y-m-d'),
            'print_ticket' => url('')."/document-pos/print/{$this->external_id}/ticket",
            'print_a4' => url('')."/document-pos/print/{$this->external_id}/a4",
            'print_a5' => url('')."/document-pos/print/{$this->external_id}/a5",
            'print_html' => url('')."/document-pos/print/{$this->external_id}/html",
            'document_pos' => $sale_note,
            'serie' => $this->series,
            'number' => $this->number,
            'has_purchase_coupon' => $has_purchase_coupon
        ];
    }


    public static function getTransformPayments($payments){

        return $payments->transform(function($row, $key){
            return [
                'id' => $row->id,
                'document_pos_id' => $row->document_pos_id,
                'date_of_payment' => $row->date_of_payment->format('Y-m-d'),
                'payment_method_type_id' => $row->payment_method_type_id,
                'has_card' => $row->has_card,
                'card_brand_id' => $row->card_brand_id,
                'reference' => $row->reference,
                'payment' => $row->payment,
                'payment_method_type' => $row->payment_method_type,
                'payment_destination_id' => ($row->global_payment) ? ($row->global_payment->type_record == 'cash' ? 'cash':$row->global_payment->destination_id):null,
                'payment_filename' => ($row->payment_file) ? $row->payment_file->filename:null,
            ];
        });

    }
}
