<template>
    <div>
        <div class="row ">
            <div class="col-md-12 col-lg-12 col-xl-12 ">
            </div>

            <div class="col-md-12">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                        <slot name="heading"></slot>
                        </thead>
                        <tbody>
                        <slot v-for="(row, index) in records" :row="row" :index="customIndex(index)"></slot>
                        </tbody>
                    </table>
                    <div>
                        <el-pagination
                                @current-change="getRecords"
                                layout="total, prev, pager, next"
                                :total="pagination.total"
                                :current-page.sync="pagination.current_page"
                                :page-size="pagination.per_page">
                        </el-pagination>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import moment from 'moment'
    import queryString from 'query-string'

    export default {
        props: {
            resource: String,
            applyFilter:{
                type: Boolean,
                default: true,
                required: false
            }
        },
        data () {
            return {
                search: {
                    column: null,
                    value: null
                },
                columns: [],
                records: [],
                pagination: {}
            }
        },
        computed: {
        },
        created() {
            this.$eventHub.$on('reloadData', () => {
                this.getRecords()
            })
        },
        async mounted () {
            let column_resource = _.split(this.resource, '/')
           // console.log(column_resource)
            await this.getRecords()

        },
        methods: {
            customIndex(index) {
                return (this.pagination.per_page * (this.pagination.current_page - 1)) + index + 1
            },
            getRecords() {
                return this.$http.get(`/${this.resource}/records`).then((response) => {
                    this.records = response.data.data
                    this.pagination = response.data.meta
                    this.pagination.per_page = parseInt(response.data.meta.per_page)
                });
            },
            getQueryParameters() {
                return queryString.stringify({
                    page: this.pagination.current_page,
                    limit: this.limit,
                    ...this.search
                })
            },
            changeClearInput(){
                this.search.value = ''
                this.getRecords()
            }
        }
    }
</script>
