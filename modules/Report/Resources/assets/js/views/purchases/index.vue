<template>
    <div class="card mb-0 pt-2 pt-md-0">
        <div class="card-header bg-info">
            <h3 class="my-0">Consulta de Compras</h3>
        </div>
        <div class="card mb-0">
                <div class="card-body">
                    <data-table :resource="resource"  :applyCustomer="true" :colspan="8">
                        <tr slot="heading">
                            <th class="">#</th>
                            <th class="">F. Emisión</th>
                            <th class="">F. Vencimiento</th>
                            <th class="">Proveedor</th>
                            <th class="">Estado</th>
                            <th class="">Número</th>

                            <th class="">F. Pago</th>
                            <th class="text-center">Moneda</th>
                            <!-- <th>Percepcion</th> -->
                            <th class="">Total</th>
                        <tr>
                        <tr slot-scope="{ index, row }">
                            <td>{{ index }}</td>
                            <td>{{row.date_of_issue}}</td>
                            <td>{{row.date_of_due}}</td>
                            <td>{{ row.supplier_name }}<br/><small v-text="row.supplier_number"></small></td>
                            <td>{{row.state_type_description}}</td>
                            <td>{{row.number}}
                                <br/>
                                <small v-text="row.document_type_description"></small>

                            </td>
                            <td>{{row.payment_method_type_description}}</td>
                            <td class="text-center">{{ row.currency_type_id }}</td>
                            <!-- <td class="text-right">{{ (row.total_perception && row.state_type_id != '11') ? row.total_perception : '0.00' }}</td> -->

                            <td>{{ formatNumber(row.state_type_id == '11' ? '0.00' : row.total) }}</td>

                        </tr>
                    </data-table>


                </div>
        </div>

    </div>
</template>

<script>

    import DataTable from '../../components/DataTableReports.vue'

    export default {
        components: {DataTable},
        data() {
            return {
                resource: 'reports/purchases',
                form: {},

            }
        },
        methods: {
            formatNumber(number) {
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",").replace(/\.(\d{2})/, ".$1");
            }
        }
    }
</script>
