<template>
    <div>
        <div class="page-header pr-0">
            <h2><a href="/dashboard"><i class="fas fa-tachometer-alt"></i></a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Compras</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <a :href="`/${resource}/create`" class="btn btn-custom btn-sm  mt-2 mr-2"><i class="fa fa-plus-circle"></i> Nuevo</a>
                <!-- <button   @click.prevent="clickImport()" type="button" class="btn btn-custom btn-sm  mt-2 mr-2" ><i class="fa fa-upload"></i> Importar</button> -->

            </div>
        </div>
        <div class="card mb-0">
            <div class="data-table-visible-columns">
                <el-dropdown :hide-on-click="false">
                    <el-button type="primary">
                        Mostrar/Ocultar columnas<i class="el-icon-arrow-down el-icon--right"></i>
                    </el-button>
                    <el-dropdown-menu slot="dropdown">
                        <el-dropdown-item v-for="(column, index) in columns" :key="index">
                            <el-checkbox v-model="column.visible">{{ column.title }}</el-checkbox>
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </el-dropdown>
            </div>
            <div class="card-body">
                <data-table :resource="resource" :init-search="initSearch">
                    <tr slot="heading">
                        <th>#</th>
                        <th class="text-center">F. Emisión</th>
                        <th class="text-center" v-if="columns.date_of_due.visible" >F. Vencimiento</th>
                        <th>Proveedor</th>
                        <th>Estado</th>
                        <th>Estado de pago</th>
                        <th>Número</th>
                        <th v-if="columns.affected_document.visible">Documento Afectado</th>
                        <th>Productos</th>
                        <th>Pagos</th>
                        <!-- <th>F. Pago</th> -->
                        <!-- <th>Estado</th> -->
                        <th class="text-center">Moneda</th>
                        <!-- <th class="text-right">T.Exportación</th> -->
                        <th v-if="columns.total_perception.visible" >Percepcion</th>
                        <th class="text-right">Total</th>
                        <!-- <th class="text-center">Descargas</th> -->
                        <th class="text-right">Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <td>{{ index }}</td>
                        <td class="text-center">{{ row.date_of_issue }}</td>
                        <td v-if="columns.date_of_due.visible" class="text-center">{{ row.date_of_due }}</td>
                        <td>{{ row.supplier_name }}<br/><small v-text="row.supplier_number"></small></td>
                        <td>{{row.state_type_description}}</td>
                        <td>{{row.state_type_payment_description}}</td>
                        <td>{{ row.number }}<br/>
                            <small v-text="row.document_type_description"></small><br/>
                        </td>
                        <td v-if="columns.affected_document.visible">{{ row.affected_document }}</td>
                        <td>

                            <el-popover
                                placement="right"
                                width="400"
                                trigger="click">
                                <el-table :data="row.items">
                                    <el-table-column width="80" property="key" label="#"></el-table-column>
                                    <el-table-column width="220" property="name" label="Nombre"></el-table-column>
                                    <el-table-column width="90" property="quantity" label="Cantidad"></el-table-column>
                                </el-table>
                                <el-button slot="reference"> <i class="fa fa-eye"></i></el-button>
                            </el-popover>

                        </td>
                        <!-- <td>{{ row.payment_method_type_description }}</td> -->
                        <!-- <td>
                            <template v-for="(it,ind) in row.payments">
                                {{it.payment_method_type_description}} - {{it.payment}}
                            </template>
                        </td> -->
                        <!-- <td>{{ row.state_type_description }}</td> -->
                        <td class="text-right">
                            <button
                                v-if="row.state_type_id != '11'"
                                type="button"
                                style="min-width: 41px"
                                class="btn waves-effect waves-light btn-xs btn-info m-1__2"
                                @click.prevent="clickPurchasePayment(row.id)"
                            >Pagos</button>
                        </td>

                        <td class="text-center">{{ row.currency_type_id }}</td>
                        <!-- <td class="text-right">{{ row.total_exportation }}</td> -->
                        <td v-if="columns.total_perception.visible" class="text-right">{{ formatNumber(row.total_perception ? row.total_perception : 0) }}</td>
                        <td class="text-right">{{ formatNumber(row.total) }}</td>
                        <td>
                            <a v-if="row.state_type_id != '11'" :href="`/${resource}/edit/${row.id}`" type="button" class="btn waves-effect waves-light btn-xs btn-info">Editar</a>
                            <a v-if="row.state_type_id != '11' && !['07', '08'].includes(String(row.document_type_id))" :href="`/${resource}/note/${row.id}`" type="button" class="btn waves-effect waves-light btn-xs btn-warning">Nota</a>
                            <button v-if="row.state_type_id != '11'" type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickAnulate(row.id)">Anular</button>
                            <button v-if="row.state_type_id == '11'" type="button" class="btn waves-effect waves-light btn-xs btn-danger" @click.prevent="clickDelete(row.id)">Eliminar</button>
                            <a :href="`/${resource}/pdf/${row.id}`" type="button" class="btn waves-effect waves-light btn-xs btn-info" target="_blank">PDF</a>
                            <a :href="`/${resource}/acta/${row.id}`" type="button" class="btn waves-effect waves-light btn-xs btn-success" target="_blank">Acta</a>

                        </td>
                    </tr>
                </data-table>
            </div>

            <!-- <documents-voided :showDialog.sync="showDialogVoided"
                            :recordId="recordId"></documents-voided>

            <document-options :showDialog.sync="showDialogOptions"
                              :recordId="recordId"
                              :showClose="true"></document-options> -->

            <purchase-import :showDialog.sync="showImportDialog"></purchase-import>
        </div>


        <purchase-payments
            :showDialog.sync="showDialogPurchasePayments"
            :purchaseId="recordId"
            :external="true"
            ></purchase-payments>
    </div>
</template>

<script>

    // import DocumentsVoided from './partials/voided.vue'
    // import DocumentOptions from './partials/options.vue'
    import DataTable from '../../../components/DataTable.vue'
    import {deletable} from '../../../mixins/deletable'
    import PurchaseImport from './import.vue'
    import PurchasePayments from '@viewsModulePurchase/purchase_payments/payments.vue'


    export default {
        mixins: [deletable],
        // components: {DocumentsVoided, DocumentOptions, DataTable},
        components: {DataTable, PurchaseImport, PurchasePayments},
        data() {
            return {
                showDialogVoided: false,
                resource: 'purchases',
                recordId: null,
                showDialogOptions: false,
                showDialogPurchasePayments: false,
                showImportDialog: false,
                initSearch: {
                    column: 'date_of_issue',
                    value: this.getCurrentMonth()
                },
                columns: {
                    date_of_due: {
                        title: 'F. Vencimiento',
                        visible: false
                    },
                    // total_free: {
                    //     title: 'T.Gratuita',
                    //     visible: false
                    // },
                    // total_unaffected: {
                    //     title: 'T.Inafecta',
                    //     visible: false
                    // },
                    // total_exonerated: {
                    //     title: 'T.Exonerado',
                    //     visible: false
                    // },
                    // total_taxed: {
                    //     title: 'T.Gravado',
                    //     visible: false
                    // },
                    // total_igv: {
                    //     title: 'T.Igv',
                    //     visible: false
                    // },
                    total_perception:{
                        title: 'Percepcion',
                        visible: false
                    },
                    affected_document: {
                        title: 'Documento Afectado',
                        visible: false
                    }

                }
            }
        },
        created() {
        },
        methods: {
            clickPurchasePayment(recordId) {
                this.recordId = recordId;
                this.showDialogPurchasePayments = true
            },
            clickVoided(recordId = null) {
                this.recordId = recordId
                this.showDialogVoided = true
            },
            clickDownload(download) {
                window.open(download, '_blank');
            },
            clickOptions(recordId = null) {
                this.recordId = recordId
                this.showDialogOptions = true
            },
            clickAnulate(id)
            {
                this.anular(`/${this.resource}/anular/${id}`).then(() =>
                    this.$eventHub.$emit('reloadData')
                )
            },
            clickDelete(id)
            {
                this.delete(`/${this.resource}/delete/${id}`).then(() =>
                    this.$eventHub.$emit('reloadData')
                )
            },
             clickImport() {
                this.showImportDialog = true
            },
            formatNumber(number) {
                return number ? new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(number) : '0.00'
            },
            getCurrentMonth() {
                const date = new Date();
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                return `${year}-${month}`;
            }
        }
    }
</script>
