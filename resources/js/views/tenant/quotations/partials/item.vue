<template>
    <el-dialog :title="titleDialog" :visible="showDialog" @open="create" @close="close">
        <form autocomplete="off" @submit.prevent="clickAddItem">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-7 col-lg-7 col-xl-7 col-sm-7">
                        <div class="form-group" id="custom-select"  :class="{'has-danger': errors.item_id}">
                            <label class="control-label">
                                Producto/Servicio
                                <a href="#" @click.prevent="showDialogNewItem = true">[+ Nuevo]</a>
                            </label>

                            <template  id="select-append">
                                <el-input id="custom-input">
                                    <el-select
                                        v-model="form.item_id" @change="changeItem"
                                        filterable
                                        placeholder="Buscar"
                                        popper-class="el-select-items"
                                        ref="select_item"
                                        @focus="focusSelectItem"
                                        slot="prepend"
                                        id="select-width"
                                        remote
                                        :remote-method="searchRemoteItems"
                                        :loading="loading_search">
                                        <el-option v-for="option in items" :key="option.id" :value="option.id" :label="option.full_description"></el-option>
                                    </el-select>
                                    <el-tooltip slot="append" class="item" effect="dark" content="Ver Stock del Producto" placement="bottom" >
                                        <el-button @click.prevent="clickWarehouseDetail()"><i class="fa fa-search"></i></el-button>
                                    </el-tooltip>
                                </el-input>
                            </template>

                            <small class="form-control-feedback" v-if="errors.item_id" v-text="errors.item_id[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="form-group" :class="{'has-danger': errors.tax_id}">
                            <label class="control-label">Impuesto</label>
                            <el-select v-model="form.tax_id"  filterable>
                                <el-option v-for="option in itemTaxes" :key="option.id" :value="option.id" :label="option.name"></el-option>
                            </el-select>
                            <!-- <el-checkbox :disabled="recordItem != null" v-model="change_tax_id">Editar</el-checkbox> -->
                            <el-checkbox v-model="tax_included_in_price" @change="change_price_tax_included">Impuesto incluido en el precio.</el-checkbox><br>
                            <small class="form-control-feedback" v-if="errors.tax_id" v-text="errors.tax_id[0]"></small>
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="form-group" :class="{'has-danger': errors.quantity}">
                            <label class="control-label">Cantidad</label>
                            <el-input-number v-model="form.quantity" :min="0.01" :disabled="form.item.calculate_quantity"></el-input-number>
                            <small class="form-control-feedback" v-if="errors.quantity" v-text="errors.quantity[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group" :class="{'has-danger': errors.unit_price}">
                            <label class="control-label">Precio Unitario</label>
                            <el-input v-model="form.unit_price" @input="calculateQuantity">
                                <template slot="prepend" v-if="form.item.currency_type_symbol">{{ form.item.currency_type_symbol }}</template>
                            </el-input>
                            <small class="form-control-feedback" v-if="errors.unit_price" v-text="errors.unit_price[0]"></small>
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-6">
                        <div class="form-group"  :class="{'has-danger': errors.discount}">
                            <label class="control-label">Descuento</label>
                            <el-input v-model="form.discount" :min="0" >
                                <template slot="prepend" v-if="form.item.currency_type_symbol">{{ form.item.currency_type_symbol }}</template>
                            </el-input>
                            <small class="form-control-feedback" v-if="errors.discount" v-text="errors.discount[0]"></small>
                        </div>
                    </div>


                      <div class="col-md-12"  v-if="item_unit_types.length > 0">
                        <div style="margin:3px" class="table-responsive">
                            <h3>Lista de Precios</h3>
                            <table class="table">
                                <thead>
                                <tr>
                                    <th class="text-center">Unidad</th>
                                    <th class="text-center">Descripción</th>
                                    <th class="text-center">Factor</th>
                                    <th class="text-center">Precio 1</th>
                                    <th class="text-center">Precio 2</th>
                                    <th class="text-center">Precio 3</th>
                                    <th class="text-center">Precio Default</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in item_unit_types" :key="index">
                                        <td class="text-center">{{row.unit_type.name}}</td>
                                        <td class="text-center">{{row.description}}</td>
                                        <td class="text-center">{{row.quantity_unit}}</td>
                                        <td class="text-center">{{row.price1}}</td>
                                        <td class="text-center">{{row.price2}}</td>
                                        <td class="text-center">{{row.price3}}</td>
                                        <td class="text-center">Precio {{row.price_default}}</td>
                                        <td class="series-table-actions text-right">
                                        <button type="button" class="btn waves-effect waves-light btn-xs btn-success" @click.prevent="selectedPrice(row)">
                                                <i class="el-icon-check"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions text-right pt-2">
                <el-button @click.prevent="close()">Cerrar</el-button>
                <el-button type="primary" native-type="submit" v-if="form.item_id">Agregar</el-button>
            </div>
        </form>
        <item-form :showDialog.sync="showDialogNewItem"
                   :external="true"></item-form>


        <warehouses-detail
                :showDialog.sync="showWarehousesDetail"
                :warehouses="warehousesDetail">
            </warehouses-detail>
    </el-dialog>
</template>
<style>
.el-select-dropdown {
    max-width: 80% !important;
    margin-right: 5% !important;
}
</style>
<script>

    import itemForm from '../../items/form.vue'
    import {calculateRowItem} from '../../../../helpers/functions'
    import WarehousesDetail from './warehouses.vue'

    export default {
        props: ['showDialog', 'currencyTypeIdActive', 'exchangeRateSale'],
        components: {itemForm, WarehousesDetail},
        data() {
            return {
                titleDialog: 'Agregar Producto o Servicio',
                resource: 'quotations',
                showDialogNewItem: false,
                showWarehousesDetail: false,
                errors: {},
                form: {},
                items: [],
                aux_items: [],
                affectation_igv_types: [],
                system_isc_types: [],
                discount_types: [],
                charge_types: [],
                attribute_types: [],
                use_price: 1,
                tax_included_in_price: false,
                change_affectation_igv_type_id: false,
                total_item: 0,
                has_list_prices: false,
                warehousesDetail:[],
                item_unit_types: [],
                taxes:[],
                item_unit_type: {},
                loading_search:false
            }
        },
        computed: {
            itemTaxes() {
                return this.taxes.filter(tax => !tax.is_retention);
            },
        },
        created() {
            this.initForm()
            this.$http.get(`/${this.resource}/item/tables`).then(response => {
                this.items = response.data.items
                this.taxes = response.data.taxes;

                // Si hay un item para editar, inicializamos el formulario con sus datos
                if (this.recordItem) {
                    this.initFormEdit()
                }
            })
            this.$eventHub.$on('reloadDataItems', (item_id) => {
                this.reloadDataItems(item_id)
            })
        },
        methods: {

            clickWarehouseDetail(){

                if(!this.form.item_id){
                    return this.$message.error('Seleccione un item');
                }

                let item = _.find(this.items, {'id': this.form.item_id});

                this.warehousesDetail = item.warehouses
                this.showWarehousesDetail = true
            },
            filterItems(){
                // this.items = this.items.filter(item => item.warehouses.length >0)
            },
            initForm() {
                this.errors = {};

                this.form = {
                    item_id: null,
                    item: {},
                    quantity: 1,
                    unit_price: 0,
                    item_unit_type_id: null,
                    item_unit_types: [],
                    is_set: false,

                    subtotal: null,
                    tax: {},
                    tax_id: null,
                    total: 0,
                    total_tax: 0,
                    unit_type: {},
                    discount: 0,
                    unit_type_id: null,
                };

                this.total_item = 0;
                this.item_unit_type = {};
                this.has_list_prices = false;
                this.item_unit_types = [];
                this.tax_included_in_price = true;
            },
            initFormEdit() {
                if (!this.recordItem) return

                this.form = {
                    item_id: this.recordItem.item.id,
                    item: this.recordItem.item,
                    quantity: this.recordItem.quantity,
                    unit_price: this.recordItem.unit_price,
                    item_unit_type_id: this.recordItem.item_unit_type_id,
                    item_unit_types: this.recordItem.item.item_unit_types || [],
                    is_set: this.recordItem.is_set || false,
                    subtotal: this.recordItem.subtotal,
                    tax: this.recordItem.tax,
                    tax_id: this.recordItem.tax.id,
                    total: this.recordItem.total,
                    total_tax: this.recordItem.total_tax,
                    unit_type: this.recordItem.unit_type,
                    discount: this.recordItem.discount,
                    unit_type_id: this.recordItem.unit_type_id,
                }

                this.item_unit_types = this.recordItem.item.item_unit_types || []
                this.tax_included_in_price = this.recordItem.tax_included_in_price || false
                
                if (this.recordItem.item.presentation) {
                    this.item_unit_type = this.recordItem.item.presentation
                }
            },
            create() {
            //     this.initializeFields()
            },
            close() {
                this.initForm()
                this.$emit('update:showDialog', false)
            },
            changeItem() {

                this.getItems();
                this.form.item = _.find(this.items, {'id': this.form.item_id});
                this.form.unit_price = this.form.item.sale_unit_price;

                this.form.quantity = 1;
                this.item_unit_types = this.form.item.item_unit_types;

                this.form.unit_type_id = this.form.item.unit_type_id
                this.form.tax_id = (this.taxes.length > 0) ? this.form.item.tax_id: null

                (this.item_unit_types.length > 0) ? this.has_list_prices = true : this.has_list_prices = false;

            },
            changePresentation() {
                let price = 0;

                this.item_unit_type = _.find(this.form.item.item_unit_types, {'id': this.form.item_unit_type_id});

                switch (this.item_unit_type.price_default) {
                    case 1: price = this.item_unit_type.price1
                        break;
                    case 2: price = this.item_unit_type.price2
                        break;
                    case 3: price = this.item_unit_type.price3
                        break;
                }

                this.form.unit_price = price;
                this.form.item.unit_type_id = this.item_unit_type.unit_type_id;
            },
            selectedPrice(row)
            {
                let valor = 0
                switch(row.price_default)
                {
                    case 1:
                        valor = row.price1
                        break
                    case 2:
                         valor = row.price2
                        break
                    case 3:
                         valor = row.price3
                        break

                }


                this.item_unit_type = row
                this.form.unit_price = valor
                this.form.item.unit_type_id = row.unit_type_id
                this.form.item_unit_type_id = row.id
            },
            clickAddItem() {

                let unit_price = this.form.unit_price;

                this.form.unit_price = unit_price;
                this.form.item.unit_price = unit_price;

                this.form.item.presentation = this.item_unit_type;

                this.form.tax = _.find(this.taxes, {'id': this.form.tax_id})
                this.form.unit_type = this.form.item.unit_type

                // this.initializeFields()
                this.$emit('add', this.form);
                this.initForm();
                this.setFocusSelectItem()
            },
            focusSelectItem(){
                this.$refs.select_item.$el.getElementsByTagName('input')[0].focus()
            },
            setFocusSelectItem(){

                this.$refs.select_item.$el.getElementsByTagName('input')[0].focus()

            },
            cleanTotalItem(){
                this.total_item = null;
            },
            calculateQuantity() {
                if(this.form.item.calculate_quantity) {
                    this.form.quantity = _.round((this.total_item / this.form.unit_price), 4)
                }
            },
            getItems() {
                this.$http.get(`/${this.resource}/item/tables`).then(response => {
                    this.items = response.data.items
                })
            },
            validateTotalItem(){

                this.errors = {}

                if(this.form.item.calculate_quantity){
                    if(this.total_item < 0.01)
                        this.$set(this.errors, 'total_item', ['total venta producto debe ser mayor a 0']);
                }

                return this.errors
            },
            reloadDataItems(item_id) {
                this.$http.get(`/${this.resource}/table/items`).then((response) => {
                    this.items = response.data
                    this.form.item_id = item_id
                    this.changeItem()
                    // this.filterItems()

                })
            },
            change_price_tax_included()
            {
                if(parseFloat(this.form.unit_price) == 0){
                    if(this.tax_included_in_price)
                        this.form.unit_price = this.form.item.sale_unit_price * (1 + (this.RateSelectedTax(this.form.tax_id) / 100))
                    else
                        this.form.unit_price = this.form.item.sale_unit_price
                }
                else{
                    if(parseFloat(this.form.unit_price) > 0){

                        if(this.tax_included_in_price)
                            this.form.unit_price = this.form.unit_price * (1 + (this.RateSelectedTax(this.form.tax_id) / 100))
                        else
                            this.form.unit_price = this.form.unit_price / (1 + (this.RateSelectedTax(this.form.tax_id) / 100))
                    }
                }
            },
            async searchRemoteItems(input) {
                if (input.length > 2) {
                    this.loading_search = true
                    let parameters = `input=${input}`
                    await this.$http.get(`/${this.resource}/search/items/?${parameters}`)
                        .then(response => {
                            this.items = response.data.items
                            this.loading_search = false
                            if(this.items.length == 0){
                                this.getItems()
                            }
                        })
                } else {
                    await this.getItems()
                }
            },
        }
    }

</script>
