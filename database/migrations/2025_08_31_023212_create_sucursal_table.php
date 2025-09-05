<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('sucursal', function (Blueprint $table) {
      $table->bigIncrements('idSucursal');
      $table->unsignedBigInteger('idEmpresa');
      $table->unsignedBigInteger('idMunicipio'); // Para XML <municipio>
      $table->string('nombre', 255);
      $table->integer('codigo');                 // 0 = casa matriz
      $table->string('direccion', 255)->nullable();
      $table->boolean('activo')->default(true);
      $table->timestamp('creado_en')->useCurrent();

      $table->unique(['idEmpresa','codigo'], 'ux_sucursal_empresa_codigo');

      $table->foreign('idEmpresa','fk_sucursal_empresa')
            ->references('idEmpresa')->on('empresa')
            ->onUpdate('cascade')->onDelete('restrict');

      $table->foreign('idMunicipio','fk_sucursal_municipio')
            ->references('idMunicipio')->on('municipio')
            ->onUpdate('cascade')->onDelete('restrict');
    });
  }
  public function down(): void { Schema::dropIfExists('sucursal'); }
};
