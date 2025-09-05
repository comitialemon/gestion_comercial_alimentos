<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('departamento', function (Blueprint $table) {
      $table->bigIncrements('idDepartamento');
      $table->unsignedBigInteger('idPais');
      $table->string('nombre', 120);
      $table->string('codigo', 10)->nullable();

      $table->unique(['idPais','nombre'], 'ux_departamento_pais_nombre');
      $table->foreign('idPais','fk_departamento_pais')
            ->references('idPais')->on('pais')
            ->onUpdate('cascade')->onDelete('restrict');
    });
  }
  public function down(): void { Schema::dropIfExists('departamento'); }
};
