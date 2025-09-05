<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('unidad_medida', function (Blueprint $table) {
      $table->bigIncrements('idUnidadMedida');
      $table->integer('codigo')->unique();
      $table->string('descripcion', 100);
    });
  }
  public function down(): void { Schema::dropIfExists('unidad_medida'); }
};
