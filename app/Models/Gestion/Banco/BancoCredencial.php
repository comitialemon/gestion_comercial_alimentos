<?php

namespace App\Models\Gestion\Banco;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class BancoCredencial extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_banco_credenciales';
    protected $primaryKey = 'IdCredencial';
    public $timestamps = false;

    // ==================== CONSTANTES ====================
    const BANCO_ECONOMICO = 'BECO';
    const BANCO_GANADERO = 'BGAN';

    protected $fillable = [
        'IdCliente',
        'CodigoBanco',
        'NombreBanco',
        'Alias',
        'UrlBase',
        'Usuario',
        'PasswordCifrado',
        'AesKey',
        'ApiKeyCifrada',
        'CuentaCredito',
        'BranchCode',
        // ✅ WEBHOOK
        'WebhookUser',
        'WebhookPasswordCifrado',
        'WebhookTokenCifrado',
        // Configuración
        'MonedaDefault',
        'Timeout',
        'Reintentos',
        'TokenCacheTtl',
        'ActivoInactivo',
        'Ambiente',
        'FechaUltimoUso',
        'UltimoError',
        'FechaUltimoError',
        'FechaCreacion',
        'FechaUltimaActualizacion',
        'IdOperadorIngresa',
        'IdOperadorActualiza',
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

    protected $hidden = [
        'PasswordCifrado',
        'AesKey',
        'ApiKeyCifrada',
        'CuentaCredito',
        'WebhookPasswordCifrado',
        'WebhookTokenCifrado',
    ];

    // =========================================================================
    // ACCESORS (descifran automáticamente)
    // =========================================================================

    public function getPasswordDescifradoAttribute(): string
    {
        return Crypt::decryptString($this->PasswordCifrado);
    }

    public function getAesKeyDescifradoAttribute(): ?string
    {
        return $this->AesKey ? Crypt::decryptString($this->AesKey) : null;
    }

    public function getApiKeyDescifradoAttribute(): ?string
    {
        return $this->ApiKeyCifrada ? Crypt::decryptString($this->ApiKeyCifrada) : null;
    }

    public function getCuentaCreditoDescifradoAttribute(): string
    {
        return Crypt::decryptString($this->CuentaCredito);
    }

    // ✅ WEBHOOK ACCESORS
    public function getWebhookPasswordDescifradoAttribute(): ?string
    {
        return $this->WebhookPasswordCifrado 
            ? Crypt::decryptString($this->WebhookPasswordCifrado) 
            : null;
    }

    public function getWebhookTokenDescifradoAttribute(): ?string
    {
        return $this->WebhookTokenCifrado 
            ? Crypt::decryptString($this->WebhookTokenCifrado) 
            : null;
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    public function cliente()
    {
        return $this->belongsTo(\App\Models\Gestion\Clientes\Cliente::class, 'IdCliente', 'IdCliente');
    }

    public function pagosQR()
    {
        return $this->hasMany(BancoPagoQR::class, 'IdCredencial', 'IdCredencial');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActivas($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopePorCliente($query, int $idCliente)
    {
        return $query->where('IdCliente', $idCliente);
    }

    public function scopePorBanco($query, string $codigoBanco)
    {
        return $query->where('CodigoBanco', $codigoBanco);
    }

    public function scopeCertificacion($query)
    {
        return $query->where('Ambiente', 'CERTIFICACION');
    }

    public function scopeProduccion($query)
    {
        return $query->where('Ambiente', 'PRODUCCION');
    }

    public function scopeConWebhook($query)
    {
        return $query->whereNotNull('WebhookTokenCifrado');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function estaActiva(): bool
    {
        return $this->ActivoInactivo == 1;
    }

    public function esCertificacion(): bool
    {
        return $this->Ambiente === 'CERTIFICACION';
    }

    public function esProduccion(): bool
    {
        return $this->Ambiente === 'PRODUCCION';
    }

    public function esBancoEconomico(): bool
    {
        return $this->CodigoBanco === self::BANCO_ECONOMICO;
    }

    public function esBancoGanadero(): bool
    {
        return $this->CodigoBanco === self::BANCO_GANADERO;
    }

    public function requiereApiKey(): bool
    {
        return $this->esBancoGanadero();
    }

    /**
     * ¿Tiene credenciales de webhook configuradas?
     */
    public function tieneWebhook(): bool
    {
        return !empty($this->WebhookUser) && !empty($this->WebhookTokenCifrado);
    }
}