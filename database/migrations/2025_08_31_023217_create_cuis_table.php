<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('cuis', function (Blueprint $table) {
      $table->bigIncrements('idCuis');
      $table->unsignedBigInteger('idPuntoVenta');
      $table->string('codigo', 255);      // CUIS
      $table->dateTime('fecha_vigencia'); // hasta cuándo es válido
      $table->boolean('activo')->default(true);
      $table->timestamp('generado_en')->useCurrent();

      $table->unique(['idPuntoVenta','codigo'], 'ux_cuis_pv_codigo');
      $table->foreign('idPuntoVenta','fk_cuis_pv')
            ->references('idPuntoVenta')->on('punto_venta')
            ->onUpdate('cascade')->onDelete('restrict');
    });
  }
  public function down(): void { Schema::dropIfExists('cuis'); }
};
