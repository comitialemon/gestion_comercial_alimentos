<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('punto_venta', function (Blueprint $table) {
      $table->bigIncrements('idPuntoVenta');
      $table->unsignedBigInteger('idSucursal');
      $table->unsignedBigInteger('idTipoPuntoVenta');
      $table->string('nombre', 255);
      $table->string('codigo', 50); // código PV del SIN
      $table->string('direccion', 255)->nullable(); // si difiere de la sucursal
      $table->boolean('es_movil')->default(false);
      $table->boolean('puede_firmar')->default(false);
      $table->boolean('activo')->default(true);
      $table->timestamp('creado_en')->useCurrent();

      $table->unique(['idSucursal','codigo'], 'ux_pv_sucursal_codigo');

      $table->foreign('idSucursal','fk_pv_sucursal')
            ->references('idSucursal')->on('sucursal')
            ->onUpdate('cascade')->onDelete('restrict');

      $table->foreign('idTipoPuntoVenta','fk_pv_tipo')
            ->references('idTipoPuntoVenta')->on('tipo_punto_venta')
            ->onUpdate('cascade')->onDelete('restrict');
    });
  }
  public function down(): void { Schema::dropIfExists('punto_venta'); }
};
