<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('map_cliente_empresa', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->unsignedBigInteger('idClienteGestion'); // gestion.todos_cliente.IdCliente
      $table->unsignedBigInteger('idEmpresa');        // facturacion.empresa.idEmpresa
      $table->unique(['idClienteGestion'], 'u_cli');
      $table->unique(['idEmpresa'], 'u_emp');
    });
  }
  public function down(): void { Schema::dropIfExists('map_cliente_empresa'); }
};
