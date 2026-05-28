<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class ClienteTema extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_cliente_tema';
    protected $primaryKey = 'id_tema';
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'color_principal',
        'color_secundario',
        'color_fondo',
        'color_texto',
        'color_acento',
        'logo_url',
        'logo_favicon',
        'nombre_sistema',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean'
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'IdCliente');
    }

    /**
     * Obtener tema por cliente (con fallback a default)
     */
    public static function getByCliente($clienteId)
    {
        $tema = self::where('id_cliente', $clienteId)
            ->where('activo', 1)
            ->first();

        if (!$tema) {
            // 🔥 Tema por defecto: NEGRO, GRIS, BLANCO
            return self::getDefaultTheme();
        }

        return $tema;
    }

    /**
     * Tema por defecto (colores NEUTROS - negro/gris/blanco)
     * Así sabemos que el cliente NO tiene tema personalizado
     */
    public static function getDefaultTheme()
    {
        $theme = new self();
        $theme->color_principal = '#1f2937';     // gray-800 (negro/gris oscuro)
        $theme->color_secundario = '#4b5563';    // gray-600 (gris medio)
        $theme->color_fondo = '#ffffff';         // blanco
        $theme->color_texto = '#000000';         // negro
        $theme->color_acento = '#6b7280';        // gray-500 (gris claro)
        $theme->nombre_sistema = 'Sistema Gestion';
        
        return $theme;
    }

    /**
     * Verificar si el cliente tiene tema personalizado (no es default)
     */
    public static function tieneTemaPersonalizado($clienteId)
    {
        return self::where('id_cliente', $clienteId)
            ->where('activo', 1)
            ->exists();
    }
}