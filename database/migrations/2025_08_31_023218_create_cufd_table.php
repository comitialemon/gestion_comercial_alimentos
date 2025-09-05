<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('cufd', function (Blueprint $table) {
      $table->bigIncrements('idCufd');
      $table->unsignedBigInteger('idCuis');
      $table->unsignedBigInteger('idPuntoVenta');
      $table->string('codigo', 255)->unique(); // CUFD
      $table->string('cufd_control', 255)->nullable();
      $table->date('fecha_vigencia');          // vigente por día
      $table->integer('contador')->default(0);
      $table->boolean('activo')->default(true);
      $table->timestamp('generado_en')->useCurrent();

      $table->unique(['idPuntoVenta','fecha_vigencia'], 'ux_cufd_pv_fecha');
      $table->foreign('idCuis','fk_cufd_cuis')
            ->references('idCuis')->on('cuis')
            ->onUpdate('cascade')->onDelete('restrict');
      $table->foreign('idPuntoVenta','fk_cufd_pv')
            ->references('idPuntoVenta')->on('punto_venta')
            ->onUpdate('cascade')->onDelete('restrict');
    });
  }
  public function down(): void { Schema::dropIfExists('cufd'); }
};
