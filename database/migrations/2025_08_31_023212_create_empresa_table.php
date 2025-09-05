<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('empresa', function (Blueprint $table) {
      $table->bigIncrements('idEmpresa');
      $table->string('nombre', 255);
      $table->string('nit', 20)->unique();
      $table->string('razon_social', 255)->nullable();
      $table->unsignedTinyInteger('modalidad')->default(1); // 1=En línea, 2=Fuera de línea
      $table->unsignedTinyInteger('ambiente')->default(1);  // 1=Pruebas, 2=Producción
      $table->string('token', 255)->nullable();
      $table->string('codigo_sistema', 255)->nullable();
      $table->boolean('activo')->default(true);
      $table->timestamp('creado_en')->useCurrent();
    });
  }
  public function down(): void { Schema::dropIfExists('empresa'); }
};
