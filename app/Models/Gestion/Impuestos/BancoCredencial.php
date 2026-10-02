<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class BancoCredencial extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_banco_credenciales';
    protected $primaryKey = 'IdCredencial';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente', 'CodigoBanco', 'NombreBanco', 'Alias',
        'UrlBase', 'Usuario', 'PasswordCifrado', 'AesKey', 'CuentaCredito', 'BranchCode',
        'MonedaDefault', 'Timeout', 'Reintentos', 'TokenCacheTtl',
        'ActivoInactivo', 'Ambiente',
        'FechaUltimoUso', 'UltimoError', 'FechaUltimoError',
        'FechaCreacion', 'FechaUltimaActualizacion',
        'IdOperadorIngresa', 'IdOperadorActualiza',
    ];

    protected $casts = [
        'ActivoInactivo' => 'boolean',
        'Timeout' => 'integer',
        'Reintentos' => 'integer',
        'TokenCacheTtl' => 'integer',
        'FechaUltimoUso' => 'datetime',
        'FechaUltimoError' => 'datetime',
        'FechaCreacion' => 'datetime',
        'FechaUltimaActualizacion' => 'datetime',
    ];

    protected $hidden = ['PasswordCifrado', 'AesKey', 'CuentaCredito'];

    // ============================
    // ACCESORS (descifran automáticamente)
    // ============================
    public function getPasswordDescifradoAttribute(): string
    {
        return Crypt::decryptString($this->PasswordCifrado);
    }

    public function getAesKeyDescifradoAttribute(): string
    {
        return Crypt::decryptString($this->AesKey);
    }

    public function getCuentaCreditoDescifradoAttribute(): string
    {
        return Crypt::decryptString($this->CuentaCredito);
    }

    // ============================
    // RELACIONES
    // ============================
    public function cliente()
    {
        return $this->belongsTo(\App\Models\Gestion\Clientes\Cliente::class, 'IdCliente', 'IdCliente');
    }

    public function pagosQR()
    {
        return $this->hasMany(BancoPagoQR::class, 'IdCredencial', 'IdCredencial');
    }

    // ============================
    // SCOPES
    // ============================
    public function scopeActivas($query) { return $query->where('ActivoInactivo', 1); }
    public function scopePorCliente($query, int $id) { return $query->where('IdCliente', $id); }
    public function scopePorBanco($query, string $codigo) { return $query->where('CodigoBanco', $codigo); }
    public function scopeCertificacion($query) { return $query->where('Ambiente', 'CERTIFICACION'); }
    public function scopeProduccion($query) { return $query->where('Ambiente', 'PRODUCCION'); }

    // ============================
    // HELPERS
    // ============================
    public function estaActiva(): bool { return $this->ActivoInactivo == 1; }
    public function esCertificacion(): bool { return $this->Ambiente === 'CERTIFICACION'; }
    public function esProduccion(): bool { return $this->Ambiente === 'PRODUCCION'; }
}