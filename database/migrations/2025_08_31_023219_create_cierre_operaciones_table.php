<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('cierre_operaciones', function (Blueprint $table) {
      $table->bigIncrements('idCierreOperacion');
      $table->unsignedBigInteger('idPuntoVenta');
      $table->date('fecha'); // día a cerrar
      $table->string('codigo_recepcion_siat', 255)->nullable();
      $table->string('estado', 50)->default('PENDIENTE'); // PENDIENTE/ENVIADO/VALIDADO/RECHAZADO
      $table->text('mensaje_respuesta')->nullable();
      $table->timestamp('creado_en')->useCurrent();

      $table->unique(['idPuntoVenta','fecha'], 'ux_cierre_pv_fecha');
      $table->foreign('idPuntoVenta','fk_cierre_pv')
            ->references('idPuntoVenta')->on('punto_venta')
            ->onUpdate('cascade')->onDelete('restrict');
    });
  }
  public function down(): void { Schema::dropIfExists('cierre_operaciones'); }
};
