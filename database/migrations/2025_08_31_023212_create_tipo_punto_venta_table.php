<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tipo_punto_venta', function (Blueprint $table) {
      $table->bigIncrements('idTipoPuntoVenta');
      $table->string('codigo', 10)->unique(); // COM, VCOB, MOV...
      $table->string('nombre', 100);
      $table->text('descripcion')->nullable();
    });
  }
  public function down(): void { Schema::dropIfExists('tipo_punto_venta'); }
};
