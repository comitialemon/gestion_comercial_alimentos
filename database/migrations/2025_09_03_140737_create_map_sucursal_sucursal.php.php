<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('map_sucursal_sucursal', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->unsignedBigInteger('idClienteSucursalGestion'); // gestion.todos_cliente_sucursal.IdClienteSucursal
      $table->unsignedBigInteger('idSucursal');               // facturacion.sucursal.idSucursal
      $table->unique(['idClienteSucursalGestion'], 'u_suc_g');
      $table->unique(['idSucursal'], 'u_suc_f');
    });
  }
  public function down(): void { Schema::dropIfExists('map_sucursal_sucursal'); }
};
