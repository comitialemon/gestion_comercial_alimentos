<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('leyenda_fiscal', function (Blueprint $table) {
      $table->bigIncrements('idLeyendaFiscal');
      $table->integer('codigo_leyenda')->unique();
      $table->text('descripcion');
    });
  }
  public function down(): void { Schema::dropIfExists('leyenda_fiscal'); }
};
