<template>
    <div class="card mb-0 pt-2 pt-md-0">
        <div class="card-header bg-info">
            <h3 class="my-0">Balance</h3>
        </div>
        <div class="card mb-0">
                <div class="card-body">
                    <data-table :resource="resource">
                        <tr slot="heading">
                            <th class="">#</th>
                            <th class=""><strong>Nombre de la cuenta / Total pagos</strong></th>
                            <th class="text-center"> <strong>Factura Electrónica</strong></th>
                            <th class="text-center"> <strong>Remisión</strong></th>
                            <th class="text-center"> <strong>Documento POS</strong></th>
                            <!-- <th class="text-center"> <strong>N. Venta</strong></th> --> 
                            <th class="text-center"> <strong>Cotización</strong></th>
                            <!-- <th class="text-center"> <strong>Contrato</strong></th> -->
                            <th class="text-center"> <strong>Ingresos</strong></th>
                            <th class="text-center"> <strong>Compras</strong></th>
                            <th class="text-center"> <strong>Gastos</strong></th>
                            <th class="text-center"> <strong>Saldo</strong></th>
                        <tr>
                        <tr slot-scope="{ index, row }">
                            <td>{{ index }}</td> 
                            <td>{{row.description}}</td>
                            <td class="text-center">{{formatNumber(row.document_payment)}}</td>
                            <td class="text-center">{{formatNumber(row.remission_payment)}}</td>
                            <td class="text-center">{{formatNumber(row.document_pos_payment)}}</td>
                            <!-- <td class="text-center">{{row.sale_note_payment}}</td> -->
                            <td class="text-center">{{formatNumber(row.quotation_payment)}}</td>
                            <!-- <td class="text-center">{{row.contract_payment}}</td> -->
                            <td class="text-center">{{formatNumber(row.income_payment)}}</td>
                            <td class="text-center">{{formatNumber(row.purchase_payment)}}</td>
                            <td class="text-center">{{formatNumber(row.expense_payment)}}</td>
                            <td class="text-center">{{formatNumber(row.balance)}}</td> 
                        </tr>
                    </data-table>
                </div>
        </div>

    </div>
</template>

<script>

    import DataTable from '../../components/DataTableWithoutPaging.vue'

    export default {
        components: {DataTable},
        data() {
            return {
                resource: 'finances/balance',
                form: {},

            }
        },
        async created() {
        },
        methods: {
            formatNumber(number) {
                return number ? new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(number) : '0.00'
            }
        }
    }
</script>
