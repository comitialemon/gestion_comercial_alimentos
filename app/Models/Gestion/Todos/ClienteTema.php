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
        'color_acento',
        'color_fondo',
        'color_texto_oscuro',
        'color_texto_claro',
        'logo_url',
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
            return self::getDefaultTheme();
        }

        return $tema;
    }

    /**
     * Tema por defecto (colores NEUTROS)
     */
    public static function getDefaultTheme()
    {
        $theme = new self();
        $theme->color_principal = '#1f2937';      // gray-800
        $theme->color_secundario = '#4b5563';     // gray-600
        $theme->color_acento = '#6b7280';         // gray-500
        $theme->color_fondo = '#ffffff';          // blanco
        $theme->color_texto_oscuro = '#111827';   // gray-900 (negro)
        $theme->color_texto_claro = '#ffffff';    // blanco
        $theme->nombre_sistema = 'Sistema Gestion';
        
        return $theme;
    }

    /**
     * Verificar si el cliente tiene tema personalizado
     */
    public static function tieneTemaPersonalizado($clienteId)
    {
        return self::where('id_cliente', $clienteId)
            ->where('activo', 1)
            ->exists();
    }
}