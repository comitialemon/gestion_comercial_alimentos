<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tipo_documento_identidad', function (Blueprint $table) {
      $table->bigIncrements('idTipoDocumentoIdentidad');
      $table->integer('codigo')->unique(); // 1=CI, 2=CEX, 3=PAS, 5=NIT...
      $table->string('descripcion', 150);
    });
  }
  public function down(): void { Schema::dropIfExists('tipo_documento_identidad'); }
};
