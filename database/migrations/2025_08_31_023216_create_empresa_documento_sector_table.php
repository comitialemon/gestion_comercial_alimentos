<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void {
    Schema::create('empresa_documento_sector', function (Blueprint $table) {
      $table->bigIncrements('idEmpresaDocumentoSector');
      $table->unsignedBigInteger('idEmpresa');
      $table->unsignedBigInteger('idDocumentoSector');
      $table->dateTime('vigente_desde')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->dateTime('vigente_hasta')->nullable();

      $table->foreign('idEmpresa','fk_eds_emp')
            ->references('idEmpresa')->on('empresa')
            ->onUpdate('cascade')->onDelete('restrict');
      $table->foreign('idDocumentoSector','fk_eds_sec')
            ->references('idDocumentoSector')->on('documento_sector')
            ->onUpdate('cascade')->onDelete('restrict');

      $table->unique(['idEmpresa','idDocumentoSector','vigente_desde'], 'ux_eds_hist');
    });
  }
  public function down(): void { Schema::dropIfExists('empresa_documento_sector'); }
};
