<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void {
    Schema::create('empresa_leyenda_fiscal', function (Blueprint $table) {
      $table->bigIncrements('idEmpresaLeyendaFiscal');
      $table->unsignedBigInteger('idEmpresa');
      $table->unsignedBigInteger('idLeyendaFiscal');
      $table->unsignedBigInteger('idDocumentoSector')->nullable(); // si varían por rubro
      $table->dateTime('vigente_desde')->default(DB::raw('CURRENT_TIMESTAMP'));
      $table->dateTime('vigente_hasta')->nullable();

      $table->foreign('idEmpresa','fk_elf_emp')
            ->references('idEmpresa')->on('empresa')
            ->onUpdate('cascade')->onDelete('restrict');
      $table->foreign('idLeyendaFiscal','fk_elf_ley')
            ->references('idLeyendaFiscal')->on('leyenda_fiscal')
            ->onUpdate('cascade')->onDelete('restrict');
      $table->foreign('idDocumentoSector','fk_elf_sec')
            ->references('idDocumentoSector')->on('documento_sector')
            ->onUpdate('cascade')->onDelete('restrict');

      $table->unique(['idEmpresa','idLeyendaFiscal','idDocumentoSector','vigente_desde'], 'ux_elf_scope_hist');
    });
  }
  public function down(): void { Schema::dropIfExists('empresa_leyenda_fiscal'); }
};
