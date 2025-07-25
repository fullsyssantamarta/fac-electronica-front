<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountingChartAccountConfigurationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounting_chart_account_configurations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('inventory_account', 10)->nullable();
            $table->string('inventory_adjustment_account', 10)->nullable();
            $table->string('sale_cost_account', 10)->nullable();
            $table->string('customer_receivable_account', 10)->nullable();
            $table->string('customer_returns_account', 10)->nullable();
            $table->string('supplier_payable_account', 10)->nullable();
            $table->string('supplier_returns_account', 10)->nullable();
            $table->string('retained_earning_account', 10)->nullable();
            $table->string('profit_period_account', 10)->nullable();
            $table->string('lost_period_account', 10)->nullable();
            $table->string('adjustment_opening_balance_banks_account', 10)->nullable();
            $table->string('adjustment_opening_balance_banks_inventory', 10)->nullable();
            $table->timestamps();
        });

        DB::table('accounting_chart_account_configurations')->insert([
            'inventory_account' => '143505',
            'inventory_adjustment_account' => '618005',
            'sale_cost_account' => '613595',
            'customer_receivable_account' => '130505',
            'customer_returns_account' => '130505',
            'supplier_payable_account' => '220505',
            'supplier_returns_account' => '220505',
            'retained_earning_account' => '370505',
            'profit_period_account' => '360505',
            'lost_period_account' => '361005',
            'adjustment_opening_balance_banks_account' => null,
            'adjustment_opening_balance_banks_inventory' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounting_chart_account_configurations');
    }
}
