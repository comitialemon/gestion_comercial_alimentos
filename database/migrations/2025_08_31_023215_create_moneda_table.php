<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('moneda', function (Blueprint $table) {
      $table->bigIncrements('idMoneda');
      $table->string('codigo', 10)->unique(); // BOB, USD
      $table->string('descripcion', 100);
    });
  }
  public function down(): void { Schema::dropIfExists('moneda'); }
};
