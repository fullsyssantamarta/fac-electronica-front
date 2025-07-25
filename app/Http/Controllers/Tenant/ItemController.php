<?php
namespace App\Http\Controllers\Tenant;

use App\Imports\ItemsImport;
use App\Models\Tenant\Catalogs\AffectationIgvType;
use App\Models\Tenant\Catalogs\AttributeType;
use App\Models\Tenant\Catalogs\CurrencyType;
use App\Models\Tenant\Catalogs\SystemIscType;
use App\Models\Tenant\Catalogs\UnitType;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemImage;
use Modules\Item\Models\ItemLot;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\Tenant\ItemRequest;
use App\Http\Resources\Tenant\ItemCollection;
use App\Http\Resources\Tenant\ItemResource;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\Configuration;
use App\Models\Tenant\ItemUnitType;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use Modules\Account\Models\Account;
use App\Models\Tenant\ItemTag;
use App\Models\Tenant\Catalogs\Tag;
use Modules\Item\Models\Category;
use Modules\Item\Models\Brand;
use Modules\Inventory\Models\Warehouse as WarehouseModule;
use App\Models\Tenant\Establishment;
use Modules\Item\Models\ItemLotsGroup;

use Modules\Factcolombia1\Models\Tenant\{
    TypeUnit,
    Currency,
    Tax,
};
use Illuminate\Support\Facades\Log;
use Modules\Item\Models\Color;
use Modules\Item\Models\Size;
use Modules\Accounting\Models\ChartAccountSaleConfiguration;


class ItemController extends Controller
{
    public function index()
    {

        return view('tenant.items.index');
    }

    public function index_ecommerce()
    {
        return view('tenant.items_ecommerce.index');
    }

    public function columns()
    {
        return [
            'name' => 'Nombre',
            'internal_id' => 'Código interno',
            'brand' => 'Marca',
            'date_of_due' => 'Fecha vencimiento',
            'lot_code' => 'Código lote',
            'active' => 'Habilitados',
            'inactive' => 'Inhabilitados',
            // 'description' => 'Descripción'
        ];
    }

    public function records(Request $request)
    {
        $records = $this->getRecords($request);

        return new ItemCollection($records->paginate(config('tenant.items_per_page')));
    }


    public function getRecords($request){

        switch ($request->column) {

            case 'brand':
                $records = Item::whereHas('brand',function($q) use($request){
                                    $q->where('name', 'like', "%{$request->value}%");
                                })
                                ->whereTypeUser()
                                ->whereNotIsSet();
                break;

            case 'active':
                $records = Item::whereTypeUser()
                                ->whereNotIsSet()
                                ->whereIsActive();
                break;

            case 'inactive':
                $records = Item::whereTypeUser()
                                ->whereNotIsSet()
                                ->whereIsNotActive();
                break;

            default:
                $records = Item::whereTypeUser()
                                ->whereNotIsSet()
                                ->where($request->column, 'like', "%{$request->value}%");
                break;

        }

        return $records->orderBy('description');

    }

    public function create()
    {
        return view('tenant.items.form');
    }

    public function tables()
    {
        // $unit_types = UnitType::whereActive()->orderByDescription()->get();
        $unit_types = TypeUnit::get();
        $taxes = Tax::query()->where('is_retention', false)->get();
        $currency_types = Currency::get();

        // $currency_types = CurrencyType::whereActive()->orderByDescription()->get();
        $attribute_types = AttributeType::whereActive()->orderByDescription()->get();
        // $system_isc_types = SystemIscType::whereActive()->orderByDescription()->get();
        // $affectation_igv_types = AffectationIgvType::whereActive()->get();
        // $warehouse = Warehouse::where('establishment_id', auth()->user()->establishment_id)->first();
        $warehouses = Warehouse::all();
        $accounts = Account::all();
        $tags = Tag::all();
        $categories = Category::all();
        $brands = Brand::all();
        $colors = Color::all();
        $sizes = Size::all();
        $configuration = Configuration::select('affectation_igv_type_id')->firstOrFail();
        $account_sale_configurations = ChartAccountSaleConfiguration::all();

        return compact('unit_types', 'attribute_types','warehouses', 'accounts', 'tags', 'categories', 'brands',
                        'configuration', 'taxes', 'currency_types','colors','sizes','account_sale_configurations');
    }

    public function record($id)
    {
        $record = new ItemResource(Item::findOrFail($id));

        return $record;
    }

    public function store(ItemRequest $request) {
        //return 'no';
        $id = $request->input('id');
        $item = Item::firstOrNew(['id' => $id]);
        $item->item_type_id = '01';
        $item->amount_plastic_bag_taxes = Configuration::firstOrFail()->amount_plastic_bag_taxes;
        $item->fill($request->all());

        $temp_path = $request->input('temp_path');
        if($temp_path) {

            $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR;

            $file_name_old = $request->input('image');
            $file_name_old_array = explode('.', $file_name_old);
            $file_content = file_get_contents($temp_path);
            $datenow = date('YmdHis');
            $file_name = Str::slug($item->description).'-'.$datenow.'.'.$file_name_old_array[1];
            Storage::put($directory.$file_name, $file_content);
            $item->image = $file_name;

            //--- IMAGE SIZE MEDIUM
            $image = \Image::make($temp_path);
            $file_name = Str::slug($item->description).'-'.$datenow.'_medium'.'.'.$file_name_old_array[1];
            $image->resize(512, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            Storage::put($directory.$file_name,  (string) $image->encode('jpg', 30));
            $item->image_medium = $file_name;

              //--- IMAGE SIZE SMALL
            $image = \Image::make($temp_path);
            $file_name = Str::slug($item->description).'-'.$datenow.'_small'.'.'.$file_name_old_array[1];
            $image->resize(256, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            Storage::put($directory.$file_name,  (string) $image->encode('jpg', 20));
            $item->image_small = $file_name;



        }else if(!$request->input('image') && !$request->input('temp_path') && !$request->input('image_url')){
            $item->image = 'imagen-no-disponible.jpg';
        }

        $item->save();

        foreach ($request->item_unit_types as $value) {

            $item_unit_type = ItemUnitType::firstOrNew(['id' => $value['id']]);
            $item_unit_type->item_id = $item->id;
            $item_unit_type->description = $value['description'];
            $item_unit_type->unit_type_id = $value['unit_type_id'];
            $item_unit_type->quantity_unit = $value['quantity_unit'];
            $item_unit_type->price1 = $value['price1'];
            $item_unit_type->price2 = $value['price2'];
            $item_unit_type->price3 = $value['price3'];
            $item_unit_type->price_default = $value['price_default'];
            $item_unit_type->save();

        }

        if($request->tags_id)
        {
            ItemTag::destroy(   ItemTag::where('item_id', $item->id)->pluck('id'));
            foreach ($request->tags_id as $value) {
                ItemTag::create(['item_id' => $item->id,  'tag_id' => $value]);
                //$tag = ItemTag::where('item_id', $item->id)->where('tag_id', $value)->first();
            }
        }

        if(!$id){

            // $item->lots()->delete();
            $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
            $warehouse = Warehouse::where('establishment_id',$establishment->id)->first();

            //$warehouse = WarehouseModule::find(auth()->user()->establishment_id);

            $v_lots = isset($request->lots) ? $request->lots:[];

            foreach ($v_lots as $lot) {

                // $item->lots()->create($lot);
                $item->lots()->create([
                    'date' => $lot['date'],
                    'series' => $lot['series'],
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse ? $warehouse->id:null,
                    'has_sale' => false,
                    'state' => $lot['state'],
                ]);
            }


            $lots_enabled = isset($request->lots_enabled) ? $request->lots_enabled:false;

            if($lots_enabled)
            {
                ItemLotsGroup::create([
                    'code'  => $request->lot_code,
                    'quantity'  => $request->stock,
                    'date_of_due'  => $request->date_of_due,
                    'item_id' => $item->id
                ]);
            }


        }
        else{

             // $item->lots()->delete();
             $establishment = Establishment::where('id', auth()->user()->establishment_id)->first();
             $warehouse = Warehouse::where('establishment_id',$establishment->id)->first();
             //$warehouse = WarehouseModule::find(auth()->user()->establishment_id);

             $v_lots = isset($request->lots) ? $request->lots:[];

             foreach ($v_lots as $lot) {

                if($lot['deleted'] == true){

                    ItemLot::find($lot['id'])->delete();
                }
                else{

                    if( isset( $lot['id'] ))
                    {
                        ItemLot::find($lot['id'])->update([
                            'date' => $lot['date'],
                            'series' => $lot['series'],
                            'state' => $lot['state'],
                        ]);

                    }else{

                        $item->lots()->create([
                            'date' => $lot['date'],
                            'series' => $lot['series'],
                            'item_id' => $item->id,
                            'warehouse_id' => $warehouse ? $warehouse->id:null,
                            'has_sale' => false,
                            'state' => $lot['state'],
                        ]);
                    }

                }


             }


        }

            $directory = 'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR;

            $multi_images = isset($request->multi_images) ? $request->multi_images:[];

            foreach ($multi_images as $im) {

                $file_name = $im['filename'];
                $file_content = file_get_contents($im['temp_path']);
                Storage::put($directory.$file_name, $file_content);

                ItemImage::create(['item_id'=> $item->id, 'image' => $file_name]);
            }

        $item->update();

        return [
            'success' => true,
            'message' => ($id)?'Producto editado con éxito':'Producto registrado con éxito',
            'id' => $item->id
        ];
    }

    public function destroy($id)
    {
        try {

            $item = Item::findOrFail($id);
            $this->deleteRecordInitialKardex($item);
            $item->delete();

            return [
                'success' => true,
                'message' => 'Producto eliminado con éxito'
            ];

        } catch (Exception $e) {

            return ($e->getCode() == '23000') ? ['success' => false,'message' => 'El producto esta siendo usado por otros registros, no puede eliminar'] : ['success' => false,'message' => 'Error inesperado, no se pudo eliminar el producto'];

        }


    }


    /**
     * Eliminar todos los productos que no tienen registros asociados
     *
     * @return array
     */
    public function deleteAll()
    {

        $quantity_deleted = 0;
        $items = Item::select('id', 'name')->get();

        foreach ($items as $item) {

            // si los productos tienen registros asociados no se eliminan
            try {

                $this->deleteRecordInitialKardex($item);
                $item->delete();
                $quantity_deleted++;

            } catch (Exception $e)
            {
                // Log::info("El producto {$item->name} no pudo ser eliminado: Code - {$e->getCode()} | Message - {$e->getMessage()} | Line - {$e->getLine()}");
            }

        }

        return [
            'success' => true,
            'message' => "{$quantity_deleted} producto(s) eliminados"
        ];

    }


    public function destroyItemUnitType($id)
    {
        $item_unit_type = ItemUnitType::findOrFail($id);
        $item_unit_type->delete();

        return [
            'success' => true,
            'message' => 'Registro eliminado con éxito'
        ];
    }


    public function import(Request $request)
    {
        \Log::debug("A");
        if ($request->hasFile('file')) {
            try {
                $import = new ItemsImport();
                $import->import($request->file('file'), null, Excel::XLSX);
                $data = $import->getData();
                return [
                    'success' => true,
                    'message' =>  __('app.actions.upload.success'),
                    'data' => $data
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' =>  $e->getMessage()
                ];
            }
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

    public function upload(Request $request)
    {
        if ($request->hasFile('file')) {
            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
            ];

            return $this->upload_image($new_request);
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

    function upload_image($request)
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

    private function deleteRecordInitialKardex($item){

        if($item->kardex->count() == 1){
            ($item->kardex[0]->type == null) ? $item->kardex[0]->delete() : false;
        }

    }


    public function visibleStore(Request $request)
    {
        $item = Item::find($request->id);

        if(!$item->internal_id && $request->apply_store){
            return [
                'success' => false,
                'message' =>'Para habilitar la visibilidad, debe asignar un codigo interno al producto',
            ];
        }

        $visible = $request->apply_store == true ? 1 : 0 ;
        $item->apply_store = $visible;
        $item->save();

        return [
            'success' => true,
            'message' => ($visible > 0 )?'El Producto ya es visible en tienda virtual' : 'El Producto ya no es visible en tienda virtual',
            'id' => $request->id
        ];

    }

    public function duplicate(Request $request)
    {
       // return $request->id;
       $obj = Item::find($request->id);
       $new = $obj->replicate();
       $new->name = date('His').'-'.$obj->name;
       $new->internal_id = date('His').'-'.$obj->internal_id;
       $new->save();

        return [
            'success' => true,
            'data' => [
                'id' => $new->id,
            ],
        ];

    }

    public function disable($id)
    {
        try {

            $item = Item::findOrFail($id);
            $item->active = 0;
            $item->save();

            return [
                'success' => true,
                'message' => 'Producto inhabilitado con éxito'
            ];

        } catch (Exception $e) {

            return  ['success' => false, 'message' => 'Error inesperado, no se pudo inhabilitar el producto'];

        }
    }

    public function images($item)
    {
        $records = ItemImage::where('item_id', $item)->get()->transform(function($row){
            return [
                'id' => $row->id,
                'item_id' => $row->item_id,
                'image' => $row->image,
                'id' => $row->id,
                'name' => $row->image,
                'url'=> asset('storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'items'.DIRECTORY_SEPARATOR.$row->image)
            ];
        });
        return [
            'success' => true,
            'data' => $records
        ];
    }

    public function delete_images($id)
    {
        $record = ItemImage::findOrFail($id);
        $record->delete();

        return [
            'success' => true,
            'message' => 'Imagen eliminada con éxito'
        ];
    }


    public function enable($id)
    {
        try {

            $item = Item::findOrFail($id);
            $item->active = 1;
            $item->save();

            return [
                'success' => true,
                'message' => 'Producto habilitado con éxito'
            ];

        } catch (Exception $e) {

            return  ['success' => false, 'message' => 'Error inesperado, no se pudo habilitar el producto'];

        }
    }


    /**
     * Busqueda de producto por id
     *
     * @param  int $id
     * @return array
     */
    public function searchItemById($id)
    {

        return [
            'items' => Item::where('id', $id)
                            ->whereFilterAllowedItem()
                            ->take(1)
                            ->get()->transform(function($row){
                                return $row->getRowSearchResource();
                            })
        ];

    }

    /**
     * Busqueda de productos
     * Si no ingresan datos para búsqueda, retorna los 10 primeros (usar en método tables)
     *
     * Usado en:
     * RemissionController
     *
     * @param  Request $request
     * @return array
    */
    public function searchItems(Request $request)
    {
        $warehouse = WarehouseModule::where('establishment_id', auth()->user()->establishment_id)->first();

        if(!$request->has('input'))
        {
            $items = Item::whereFilterAllowedItem()
                            ->take(10);
        }
        else
        {
            $items = Item::whereFilterAllowedItem()
                            ->whereFilterSearchItem($request->input);
        }

        return [
            'items' => $items->get()->transform(function($row) use($warehouse){
                return $row->getRowSearchResource($warehouse);
            })
        ];

    }


    /**
     *
     * Busqueda de registros por coincidencia o id, data inicial,  para componente
     *
     * @param  Request $request
     * @return array
     */
    public function searchData(Request $request)
    {
        $id = $request->id ?? null;
        $input = $request->input ?? null;
        $input = $request->input ?? null;
        $filter_warehouse = $request->has('filter_warehouse') && (bool) $request->filter_warehouse;

        $warehouse = WarehouseModule::where('establishment_id', auth()->user()->establishment_id)->first();

        $records = Item::query();

        if($id)
        {
            $records->where('id', $id)->take(self::TAKE_FOR_SEARCH_ID);
        }
        else if($input)
        {
            $records->whereFilterSearchItem($request->input)
                    ->optionalFiltersSearchData($filter_warehouse)
                    ->take(self::MAX_ITEMS_IN_SELECT);
        }
        else
        {
            $records->optionalFiltersSearchData($filter_warehouse)
                    ->take(self::MIN_ITEMS_IN_SELECT);
        }

        return $records->get()
                        ->transform(function($row) use($warehouse) {
                            return $row->getRowSearchResource($warehouse);
                        });
    }

}
