<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('municipio', function (Blueprint $table) {
      $table->bigIncrements('idMunicipio');
      $table->unsignedBigInteger('idDepartamento');
      $table->string('nombre', 120);
      $table->string('codigo', 10)->nullable();

      $table->unique(['idDepartamento','nombre'], 'ux_municipio_depto_nombre');
      $table->foreign('idDepartamento','fk_municipio_departamento')
            ->references('idDepartamento')->on('departamento')
            ->onUpdate('cascade')->onDelete('restrict');
    });
  }
  public function down(): void { Schema::dropIfExists('municipio'); }
};
