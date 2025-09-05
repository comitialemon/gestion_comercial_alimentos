<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('pais', function (Blueprint $table) {
      $table->bigIncrements('idPais');
      $table->string('nombre', 120);
      $table->string('iso2', 2)->nullable(); // BO, AR...
      $table->unique(['nombre'], 'ux_pais_nombre');
    });
  }
  public function down(): void { Schema::dropIfExists('pais'); }
};
