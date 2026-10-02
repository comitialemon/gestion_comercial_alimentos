<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\BancoCredencial;
use App\Services\Gestion\PuntoVenta\BancoEconomicoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BancoCredencialController extends Controller
{
    /**
     * Listar credenciales del cliente actual
     */
    public function index()
    {
        $idCliente = session('cliente_id');

        $credenciales = BancoCredencial::porCliente($idCliente)
            ->orderBy('CodigoBanco')
            ->orderBy('Ambiente')
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
                    'Timeout' => $cred->Timeout,
                    'Reintentos' => $cred->Reintentos,
                    'TokenCacheTtl' => $cred->TokenCacheTtl,
                    'ActivoInactivo' => (bool) $cred->ActivoInactivo,
                    'FechaUltimoUso' => $cred->FechaUltimoUso?->format('Y-m-d H:i:s'),
                    'UltimoError' => $cred->UltimoError,
                    'FechaUltimoError' => $cred->FechaUltimoError?->format('Y-m-d H:i:s'),
                    'FechaCreacion' => $cred->FechaCreacion?->format('Y-m-d H:i:s'),
                    // ⚠️ NO enviamos PasswordCifrado, AesKey ni CuentaCredito
                ];
            });

        return Inertia::render('Gestion/Impuestos/BancoCredenciales/Index', [
            'credenciales' => $credenciales,
            'bancosDisponibles' => [
                ['codigo' => 'BECO', 'nombre' => 'Banco Económico S.A.'],
            ],
            'ambientesDisponibles' => [
                ['valor' => 'CERTIFICACION', 'nombre' => 'Certificación'],
                ['valor' => 'PRODUCCION', 'nombre' => 'Producción'],
            ],
            'urlsDisponibles' => [
                'CERTIFICACION' => config('banco_economico.urls.CERTIFICACION'),
                'PRODUCCION' => config('banco_economico.urls.PRODUCCION'),
            ],
        ]);
    }

    /**
     * Crear nueva credencial
     *
     * ⚠️ IMPORTANTE: El password se cifra con el BANCO antes de guardarse.
     * El campo "Password" del formulario espera TEXTO PLANO.
     */
    public function store(Request $request)
    {
        $request->validate([
            'CodigoBanco' => 'required|string|max:10',
            'NombreBanco' => 'required|string|max:100',
            'Alias' => 'nullable|string|max:100',
            'UrlBase' => 'required|url|max:255',
            'Usuario' => 'required|string|max:100',
            'Password' => 'required|string|max:500',
            'AesKey' => 'required|string|max:255',
            'CuentaCredito' => 'required|string|max:255',
            'BranchCode' => 'nullable|string|max:5',
            'MonedaDefault' => 'required|in:BOB,USD',
            'Timeout' => 'required|integer|min:5|max:120',
            'Reintentos' => 'required|integer|min:1|max:10',
            'TokenCacheTtl' => 'required|integer|min:60|max:7200',
            'Ambiente' => 'required|in:CERTIFICACION,PRODUCCION',
        ]);

        try {
            $idCliente = session('cliente_id');
            $operadorId = session('operador_id');

            // Verificar que no exista ya una credencial para ese banco y ambiente
            $existe = BancoCredencial::porCliente($idCliente)
                ->porBanco($request->CodigoBanco)
                ->where('Ambiente', $request->Ambiente)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe una credencial para ese banco y ambiente',
                    'errors' => [
                        'Ambiente' => ['Ya existe una credencial para ese banco y ambiente'],
                    ],
                ], 422);
            }

            // ============================================================
            // 🔥 PASO 1: Crear credencial TEMPORAL para instanciar el Service
            // ============================================================
            $credencialTemporal = new BancoCredencial([
                'UrlBase' => $request->UrlBase,
                'Usuario' => $request->Usuario,
                'PasswordCifrado' => Crypt::encryptString($request->Password),
                'AesKey' => Crypt::encryptString($request->AesKey),
                'CuentaCredito' => Crypt::encryptString($request->CuentaCredito),
                'BranchCode' => $request->BranchCode,
                'Timeout' => $request->Timeout,
                'Reintentos' => $request->Reintentos,
                'TokenCacheTtl' => $request->TokenCacheTtl,
            ]);

            $serviceTemp = new BancoEconomicoService($credencialTemporal);

            // ============================================================
            // 🔥 PASO 2: Cifrar el PASSWORD con el banco (/encrypt)
            // ============================================================
            $passwordCifradoBanco = $serviceTemp->encriptar($request->Password);

            Log::info('🔐 Cifrado de password para nueva credencial', [
                'password_plano_longitud' => strlen($request->Password),
                'password_cifrado_longitud' => strlen($passwordCifradoBanco),
            ]);

            // ============================================================
            // 🔥 PASO 3: Guardar credencial (password CIFRADO con el banco)
            // ============================================================
            $credencial = BancoCredencial::create([
                'IdCliente' => $idCliente,
                'CodigoBanco' => $request->CodigoBanco,
                'NombreBanco' => $request->NombreBanco,
                'Alias' => $request->Alias,
                'UrlBase' => $request->UrlBase,
                'Usuario' => $request->Usuario,
                // 🔥 Cifrado con el banco + cifrado con Crypt
                'PasswordCifrado' => Crypt::encryptString($passwordCifradoBanco),
                // AesKey y CuentaCredito solo cifradas con Crypt (se cifran al vuelo al usar)
                'AesKey' => Crypt::encryptString($request->AesKey),
                'CuentaCredito' => Crypt::encryptString($request->CuentaCredito),
                'BranchCode' => $request->BranchCode,
                'MonedaDefault' => $request->MonedaDefault,
                'Timeout' => $request->Timeout,
                'Reintentos' => $request->Reintentos,
                'TokenCacheTtl' => $request->TokenCacheTtl,
                'ActivoInactivo' => 1,
                'Ambiente' => $request->Ambiente,
                'FechaCreacion' => now(),
                'IdOperadorIngresa' => $operadorId,
            ]);

            Log::info('✅ Credencial creada', [
                'IdCredencial' => $credencial->IdCredencial,
                'IdCliente' => $idCliente,
                'CodigoBanco' => $request->CodigoBanco,
                'Ambiente' => $request->Ambiente,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Credencial creada correctamente',
                'id' => $credencial->IdCredencial,
            ]);

        } catch (\Exception $e) {
            Log::error('Error creando credencial', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'general' => [$e->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * Actualizar credencial existente
     *
     * ⚠️ IMPORTANTE: Si se envía un nuevo password, se cifra con el BANCO antes de guardar.
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'CodigoBanco' => 'required|string|max:10',
            'NombreBanco' => 'required|string|max:100',
            'Alias' => 'nullable|string|max:100',
            'UrlBase' => 'required|url|max:255',
            'Usuario' => 'required|string|max:100',
            'Password' => 'nullable|string|max:500',
            'AesKey' => 'nullable|string|max:255',
            'CuentaCredito' => 'nullable|string|max:255',
            'BranchCode' => 'nullable|string|max:5',
            'MonedaDefault' => 'required|in:BOB,USD',
            'Timeout' => 'required|integer|min:5|max:120',
            'Reintentos' => 'required|integer|min:1|max:10',
            'TokenCacheTtl' => 'required|integer|min:60|max:7200',
            'Ambiente' => 'required|in:CERTIFICACION,PRODUCCION',
            'ActivoInactivo' => 'required|boolean',
        ]);

        try {
            $idCliente = session('cliente_id');
            $operadorId = session('operador_id');

            $credencial = BancoCredencial::porCliente($idCliente)->findOrFail($id);

            $datos = [
                'CodigoBanco' => $request->CodigoBanco,
                'NombreBanco' => $request->NombreBanco,
                'Alias' => $request->Alias,
                'UrlBase' => $request->UrlBase,
                'Usuario' => $request->Usuario,
                'BranchCode' => $request->BranchCode,
                'MonedaDefault' => $request->MonedaDefault,
                'Timeout' => $request->Timeout,
                'Reintentos' => $request->Reintentos,
                'TokenCacheTtl' => $request->TokenCacheTtl,
                'Ambiente' => $request->Ambiente,
                'ActivoInactivo' => $request->ActivoInactivo,
                'FechaUltimaActualizacion' => now(),
                'IdOperadorActualiza' => $operadorId,
            ];

            // ============================================================
            // 🔥 Si se envía nuevo Password, cifrarlo con el banco
            // ============================================================
            if ($request->filled('Password')) {
                $serviceTemp = new BancoEconomicoService($credencial);
                $passwordCifradoBanco = $serviceTemp->encriptar($request->Password);
                $datos['PasswordCifrado'] = Crypt::encryptString($passwordCifradoBanco);

                Log::info('🔐 Password actualizado y cifrado con el banco', [
                    'IdCredencial' => $id,
                    'password_cifrado_longitud' => strlen($passwordCifradoBanco),
                ]);
            }

            // AesKey y CuentaCredito solo se cifran con Crypt
            if ($request->filled('AesKey')) {
                $datos['AesKey'] = Crypt::encryptString($request->AesKey);
            }
            if ($request->filled('CuentaCredito')) {
                $datos['CuentaCredito'] = Crypt::encryptString($request->CuentaCredito);
            }

            $credencial->update($datos);

            // Limpiar caché del token al actualizar credenciales
            Cache::forget("banco_economico_token_{$id}");

            Log::info('✅ Credencial actualizada', [
                'IdCredencial' => $id,
                'IdCliente' => $idCliente,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Credencial actualizada correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando credencial', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [
                    'general' => [$e->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * Eliminar credencial
     */
    public function destroy(int $id)
    {
        try {
            $idCliente = session('cliente_id');

            $credencial = BancoCredencial::porCliente($idCliente)->findOrFail($id);

            // Verificar si tiene QRs asociados
            $tieneQRs = $credencial->pagosQR()->exists();
            if ($tieneQRs) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar: tiene QRs asociados. Desactívala en su lugar.',
                ], 422);
            }

            $credencial->delete();

            // Limpiar caché del token
            Cache::forget("banco_economico_token_{$id}");

            Log::info('✅ Credencial eliminada', ['IdCredencial' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Credencial eliminada correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error eliminando credencial', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activar/Desactivar credencial
     */
    public function toggleActivo(int $id)
    {
        try {
            $idCliente = session('cliente_id');

            $credencial = BancoCredencial::porCliente($idCliente)->findOrFail($id);

            $credencial->update([
                'ActivoInactivo' => !$credencial->ActivoInactivo,
                'FechaUltimaActualizacion' => now(),
                'IdOperadorActualiza' => session('operador_id'),
            ]);

            // Limpiar caché del token
            Cache::forget("banco_economico_token_{$id}");

            return response()->json([
                'success' => true,
                'message' => $credencial->ActivoInactivo ? 'Credencial activada' : 'Credencial desactivada',
                'activo' => (bool) $credencial->ActivoInactivo,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Probar conexión con el banco
     */
    public function probarConexion(int $id)
    {
        try {
            $idCliente = session('cliente_id');

            $credencial = BancoCredencial::porCliente($idCliente)->findOrFail($id);

            // Crear instancia del service con esta credencial específica
            $service = new BancoEconomicoService($credencial);

            // Probar token (forzar renovación para probar credenciales reales)
            $token = $service->obtenerToken(true);

            // Probar encriptación
            $textoPrueba = 'PRUEBA-' . date('YmdHis');
            $cifrado = $service->encriptar($textoPrueba);

            // Actualizar fecha de último uso
            $credencial->update([
                'FechaUltimoUso' => now(),
                'UltimoError' => null,
                'FechaUltimoError' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => '✅ Conexión exitosa con el banco',
                'detalles' => [
                    'token_obtenido' => substr($token, 0, 30) . '...',
                    'texto_cifrado' => substr($cifrado, 0, 30) . '...',
                ],
            ]);

        } catch (\Exception $e) {
            // Guardar el error
            try {
                BancoCredencial::find($id)->update([
                    'UltimoError' => $e->getMessage(),
                    'FechaUltimoError' => now(),
                ]);
            } catch (\Exception $ex) {
                // Ignorar
            }

            Log::error('Error probando conexión', [
                'IdCredencial' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '❌ Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}