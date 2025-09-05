<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void {
    Schema::create('empresa_actividad_economica', function (Blueprint $table) {
      $table->bigIncrements('idEmpresaActividad');
      $table->unsignedBigInteger('idEmpresa');
      $table->unsignedBigInteger('idActividadEconomica');
      $table->dateTime('vigente_desde')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->dateTime('vigente_hasta')->nullable();

      $table->foreign('idEmpresa','fk_ea_emp')
            ->references('idEmpresa')->on('empresa')
            ->onUpdate('cascade')->onDelete('restrict');
      $table->foreign('idActividadEconomica','fk_ea_act')
            ->references('idActividadEconomica')->on('actividad_economica')
            ->onUpdate('cascade')->onDelete('restrict');

      $table->unique(['idEmpresa','idActividadEconomica','vigente_desde'], 'ux_ea_hist');
    });
  }
  public function down(): void { Schema::dropIfExists('empresa_actividad_economica'); }
};
