<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('log_sincronizacion', function (Blueprint $table) {
      $table->bigIncrements('idLogSincronizacion');
      $table->unsignedBigInteger('idEmpresa');
      $table->unsignedBigInteger('idSucursal')->nullable();
      $table->unsignedBigInteger('idPuntoVenta')->nullable();

      $table->string('tipo_operacion', 100); // p.ej. SINCRONIZAR_LEYENDAS
      $table->dateTime('fecha_sincronizacion');
      $table->string('estado', 50);          // OK / ERROR
      $table->integer('codigo_respuesta')->nullable();
      $table->text('mensaje_respuesta')->nullable();

      $table->foreign('idEmpresa','fk_log_emp')
            ->references('idEmpresa')->on('empresa')
            ->onUpdate('cascade')->onDelete('restrict');
      $table->foreign('idSucursal','fk_log_suc')
            ->references('idSucursal')->on('sucursal')
            ->onUpdate('cascade')->onDelete('restrict');
      $table->foreign('idPuntoVenta','fk_log_pv')
            ->references('idPuntoVenta')->on('punto_venta')
            ->onUpdate('cascade')->onDelete('restrict');

      $table->index(['idEmpresa','idSucursal','idPuntoVenta','tipo_operacion'], 'ix_log_scope_tipo');
    });
  }
  public function down(): void { Schema::dropIfExists('log_sincronizacion'); }
};
