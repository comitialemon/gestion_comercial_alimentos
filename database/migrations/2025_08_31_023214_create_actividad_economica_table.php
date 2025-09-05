<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('actividad_economica', function (Blueprint $table) {
      $table->bigIncrements('idActividadEconomica');
      $table->integer('codigo')->unique(); // SIAT
      $table->string('descripcion', 200);
    });
  }
  public function down(): void { Schema::dropIfExists('actividad_economica'); }
};
