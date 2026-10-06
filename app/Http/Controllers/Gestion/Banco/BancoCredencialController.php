<?php

namespace App\Http\Controllers\Gestion\Banco;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Banco\BancoCredencial;
use App\Services\Gestion\Banco\BancoFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BancoCredencialController extends Controller
{
    // ============================================================
    // CONFIGURACIÓN DE BANCOS DISPONIBLES
    // ============================================================
    private const BANCOS_DISPONIBLES = [
        ['codigo' => 'BECO', 'nombre' => 'Banco Económico S.A.'],
        ['codigo' => 'BGAN', 'nombre' => 'Banco Ganadero S.A.'],
    ];

    private const AMBIENTES_DISPONIBLES = [
        ['valor' => 'CERTIFICACION', 'nombre' => 'Certificación'],
        ['valor' => 'PRODUCCION', 'nombre' => 'Producción'],
    ];

    private function getUrlsDisponibles(): array
    {
        return [
            'BECO' => [
                'CERTIFICACION' => config('banco_economico.urls.CERTIFICACION'),
                'PRODUCCION' => config('banco_economico.urls.PRODUCCION'),
            ],
            'BGAN' => [
                'CERTIFICACION' => config('banco_ganadero.urls.CERTIFICACION'),
                'PRODUCCION' => config('banco_ganadero.urls.PRODUCCION'),
            ],
        ];
    }

    // ============================================================
    // INDEX
    // ============================================================
    public function index()
    {
        $clienteId = session('cliente_id');

        $credenciales = BancoCredencial::porCliente($clienteId)
            ->orderBy('CodigoBanco')
            ->orderBy('Alias')
            ->get()
            ->map(function ($cred) {
                return [
                    'IdCredencial' => $cred->IdCredencial,
                    'CodigoBanco' => $cred->CodigoBanco,
                    'NombreBanco' => $cred->NombreBanco,
                    'Alias' => $cred->Alias,
                    'UrlBase' => $cred->UrlBase,
                    'Usuario' => $cred->Usuario,
                    'BranchCode' => $cred->BranchCode,
                    'MonedaDefault' => $cred->MonedaDefault,
                    'Ambiente' => $cred->Ambiente,
                    'ActivoInactivo' => (bool) $cred->ActivoInactivo,
                    'FechaUltimoUso' => $cred->FechaUltimoUso?->format('d/m/Y H:i'),
                    'UltimoError' => $cred->UltimoError,
                    'FechaUltimoError' => $cred->FechaUltimoError?->format('d/m/Y H:i'),
                    'TieneWebhook' => $cred->tieneWebhook(),
                    'WebhookUser' => $cred->WebhookUser,
                ];
            });

        return Inertia::render('Gestion/Banco/Credenciales/Index', [
            'credenciales' => $credenciales,
            'bancosDisponibles' => self::BANCOS_DISPONIBLES,
            'ambientesDisponibles' => self::AMBIENTES_DISPONIBLES,
            'urlsDisponibles' => $this->getUrlsDisponibles(),
        ]);
    }

    // ============================================================
    // STORE - CREAR CREDENCIAL
    // ============================================================
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $validated = $request->validate([
            'CodigoBanco' => 'required|in:BECO,BGAN',
            'NombreBanco' => 'required|string|max:100',
            'Alias' => 'nullable|string|max:50',
            'UrlBase' => 'required|url|max:255',
            'Usuario' => 'required|string|max:100',
            'Password' => 'required|string|max:255',
            'AesKey' => 'nullable|string|max:255',
            'ApiKey' => 'nullable|string|max:500',
            'CuentaCredito' => 'required|string|max:100',
            'BranchCode' => 'nullable|string|max:5',
            'MonedaDefault' => 'nullable|in:BOB,USD',
            'Timeout' => 'nullable|integer|min:5|max:120',
            'Reintentos' => 'nullable|integer|min:1|max:10',
            'TokenCacheTtl' => 'nullable|integer|min:60|max:7200',
            'Ambiente' => 'required|in:CERTIFICACION,PRODUCCION',
            'ActivoInactivo' => 'nullable|boolean',
        ]);

        // Validaciones específicas por banco
        if ($validated['CodigoBanco'] === 'BECO' && empty($validated['AesKey'])) {
            return response()->json([
                'success' => false,
                'message' => 'El AES Key es obligatorio para Banco Económico',
                'errors' => ['AesKey' => ['AES Key es obligatorio para Banco Económico']],
            ], 422);
        }

        if ($validated['CodigoBanco'] === 'BGAN' && empty($validated['ApiKey'])) {
            return response()->json([
                'success' => false,
                'message' => 'El X-Api-Key es obligatorio para Banco Ganadero',
                'errors' => ['ApiKey' => ['X-Api-Key es obligatorio para Banco Ganadero']],
            ], 422);
        }

        DB::beginTransaction();

        try {
            $credencial = BancoCredencial::create([
                'IdCliente' => $clienteId,
                'CodigoBanco' => $validated['CodigoBanco'],
                'NombreBanco' => $validated['NombreBanco'],
                'Alias' => $validated['Alias'] ?? null,
                'UrlBase' => rtrim($validated['UrlBase'], '/'),
                'Usuario' => $validated['Usuario'],
                'PasswordCifrado' => Crypt::encryptString($validated['Password']),
                'AesKey' => !empty($validated['AesKey'])
                    ? Crypt::encryptString($validated['AesKey'])
                    : null,
                'ApiKeyCifrada' => !empty($validated['ApiKey'])
                    ? Crypt::encryptString($validated['ApiKey'])
                    : null,
                'CuentaCredito' => Crypt::encryptString($validated['CuentaCredito']),
                'BranchCode' => $validated['BranchCode'] ?? null,
                'MonedaDefault' => $validated['MonedaDefault'] ?? 'BOB',
                'Timeout' => $validated['Timeout'] ?? 20,
                'Reintentos' => $validated['Reintentos'] ?? 3,
                'TokenCacheTtl' => $validated['TokenCacheTtl'] ?? 1500,
                'Ambiente' => $validated['Ambiente'],
                'ActivoInactivo' => $validated['ActivoInactivo'] ?? true,
                'IdOperadorIngresa' => $operadorId,
                'FechaCreacion' => now(),
                'FechaUltimaActualizacion' => now(),
            ]);

            // ✅ GENERAR CREDENCIALES DE WEBHOOK SOLO PARA BGAN
            $webhookData = null;
            if ($validated['CodigoBanco'] === 'BGAN') {
                $webhookData = $this->generarCredencialesWebhook($credencial, $operadorId);
            }

            DB::commit();

            Log::info('✅ Credencial creada', [
                'IdCredencial' => $credencial->IdCredencial,
                'CodigoBanco' => $credencial->CodigoBanco,
                'TieneWebhook' => $webhookData !== null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Credencial creada correctamente' . ($webhookData ? '. Guarda las credenciales del webhook.' : ''),
                'credencial' => [
                    'IdCredencial' => $credencial->IdCredencial,
                    'CodigoBanco' => $credencial->CodigoBanco,
                    'NombreBanco' => $credencial->NombreBanco,
                ],
                'webhook' => $webhookData,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error creando credencial', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear credencial: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // UPDATE - ACTUALIZAR CREDENCIAL
    // ============================================================
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $credencial = BancoCredencial::porCliente($clienteId)->findOrFail($id);

        $validated = $request->validate([
            'CodigoBanco' => 'required|in:BECO,BGAN',
            'NombreBanco' => 'required|string|max:100',
            'Alias' => 'nullable|string|max:50',
            'UrlBase' => 'required|url|max:255',
            'Usuario' => 'required|string|max:100',
            'Password' => 'nullable|string|max:255',
            'AesKey' => 'nullable|string|max:255',
            'ApiKey' => 'nullable|string|max:500',
            'CuentaCredito' => 'nullable|string|max:100',
            'BranchCode' => 'nullable|string|max:5',
            'MonedaDefault' => 'nullable|in:BOB,USD',
            'Timeout' => 'nullable|integer|min:5|max:120',
            'Reintentos' => 'nullable|integer|min:1|max:10',
            'TokenCacheTtl' => 'nullable|integer|min:60|max:7200',
            'Ambiente' => 'required|in:CERTIFICACION,PRODUCCION',
            'ActivoInactivo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();

        try {
            $updateData = [
                'CodigoBanco' => $validated['CodigoBanco'],
                'NombreBanco' => $validated['NombreBanco'],
                'Alias' => $validated['Alias'] ?? null,
                'UrlBase' => rtrim($validated['UrlBase'], '/'),
                'Usuario' => $validated['Usuario'],
                'BranchCode' => $validated['BranchCode'] ?? null,
                'MonedaDefault' => $validated['MonedaDefault'] ?? 'BOB',
                'Timeout' => $validated['Timeout'] ?? 20,
                'Reintentos' => $validated['Reintentos'] ?? 3,
                'TokenCacheTtl' => $validated['TokenCacheTtl'] ?? 1500,
                'Ambiente' => $validated['Ambiente'],
                'ActivoInactivo' => $validated['ActivoInactivo'] ?? true,
                'IdOperadorActualiza' => $operadorId,
                'FechaUltimaActualizacion' => now(),
            ];

            if (!empty($validated['Password'])) {
                $updateData['PasswordCifrado'] = Crypt::encryptString($validated['Password']);
            }

            if (!empty($validated['AesKey'])) {
                $updateData['AesKey'] = Crypt::encryptString($validated['AesKey']);
            }

            if (!empty($validated['ApiKey'])) {
                $updateData['ApiKeyCifrada'] = Crypt::encryptString($validated['ApiKey']);
            }

            if (!empty($validated['CuentaCredito'])) {
                $updateData['CuentaCredito'] = Crypt::encryptString($validated['CuentaCredito']);
            }

            $credencial->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Credencial actualizada correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error actualizando credencial', [
                'IdCredencial' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // DESTROY - ELIMINAR CREDENCIAL
    // ============================================================
    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        $credencial = BancoCredencial::porCliente($clienteId)->findOrFail($id);

        try {
            $credencial->delete();

            return response()->json([
                'success' => true,
                'message' => 'Credencial eliminada correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error eliminando credencial', [
                'IdCredencial' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // TOGGLE ACTIVO
    // ============================================================
    public function toggleActivo($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $credencial = BancoCredencial::porCliente($clienteId)->findOrFail($id);

        $credencial->update([
            'ActivoInactivo' => !$credencial->ActivoInactivo,
            'IdOperadorActualiza' => $operadorId,
            'FechaUltimaActualizacion' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $credencial->ActivoInactivo
                ? 'Credencial activada'
                : 'Credencial desactivada',
            'activo' => (bool) $credencial->ActivoInactivo,
        ]);
    }

    // ============================================================
    // PROBAR CONEXIÓN
    // ============================================================
    public function probarConexion($id)
    {
        $clienteId = session('cliente_id');
        $credencial = BancoCredencial::porCliente($clienteId)->findOrFail($id);

        try {
            $service = BancoFactory::desdeCredencial($credencial);
            $token = $service->obtenerToken(true);

            $credencial->update([
                'FechaUltimoUso' => now(),
                'UltimoError' => null,
                'FechaUltimoError' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conexión exitosa con ' . $credencial->NombreBanco,
                'token_preview' => substr($token, 0, 20) . '...',
            ]);

        } catch (\Exception $e) {
            $credencial->update([
                'UltimoError' => substr($e->getMessage(), 0, 500),
                'FechaUltimoError' => now(),
            ]);

            Log::error('❌ Error probando conexión', [
                'IdCredencial' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // HELPER PRIVADO: GENERAR CREDENCIALES DE WEBHOOK
    // ============================================================
    private function generarCredencialesWebhook(BancoCredencial $credencial, ?int $operadorId): array
    {
        $webhookUser = 'bgan_' . $credencial->IdCredencial . '_' . Str::lower(Str::random(8));
        $webhookPassword = Str::random(48);

        $webhookToken = hash('sha256',
            $webhookUser . '|' .
            $webhookPassword . '|' .
            $credencial->IdCredencial . '|' .
            config('app.key') . '|' .
            now()->timestamp
        );

        $credencial->update([
            'WebhookUser' => $webhookUser,
            'WebhookPasswordCifrado' => Crypt::encryptString($webhookPassword),
            'WebhookTokenCifrado' => Crypt::encryptString($webhookToken),
            'IdOperadorActualiza' => $operadorId,
            'FechaUltimaActualizacion' => now(),
        ]);

        Log::info('🔐 Credenciales webhook generadas', [
            'IdCredencial' => $credencial->IdCredencial,
            'WebhookUser' => $webhookUser,
        ]);

        return [
            'user' => $webhookUser,
            'password' => $webhookPassword,
            'token' => $webhookToken,
            'login_url' => url('/api/banco-ganadero/login'),
            'payments_url' => url('/api/banco-ganadero/payments'),
        ];
    }

    // ============================================================
    // REGENERAR CREDENCIALES DE WEBHOOK
    // ============================================================
    public function regenerarWebhook($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $credencial = BancoCredencial::porCliente($clienteId)->findOrFail($id);

        if ($credencial->CodigoBanco !== 'BGAN') {
            return response()->json([
                'success' => false,
                'message' => 'Solo Banco Ganadero usa webhook',
            ], 422);
        }

        try {
            DB::beginTransaction();
            $webhookData = $this->generarCredencialesWebhook($credencial, $operadorId);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Credenciales del webhook regeneradas. Las anteriores ya no funcionarán.',
                'webhook' => $webhookData,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}