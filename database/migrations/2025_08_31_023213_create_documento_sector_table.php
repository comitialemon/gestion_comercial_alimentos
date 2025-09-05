<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('documento_sector', function (Blueprint $table) {
      $table->bigIncrements('idDocumentoSector');
      $table->integer('codigo')->unique(); // código SIAT del sector
      $table->string('descripcion', 200);
    });
  }
  public function down(): void { Schema::dropIfExists('documento_sector'); }
};
