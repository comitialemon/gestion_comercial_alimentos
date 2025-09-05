<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('metodo_pago', function (Blueprint $table) {
      $table->bigIncrements('idMetodoPago');
      $table->integer('codigo')->unique();
      $table->string('descripcion', 150);
    });
  }
  public function down(): void { Schema::dropIfExists('metodo_pago'); }
};
