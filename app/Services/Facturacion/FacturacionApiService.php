<?php

namespace App\Services\Facturacion;

use Illuminate\Support\Facades\Http;

class FacturacionApiService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('FACTURACION_API_URL', 'http://siat-app:80');
    }

    public function getMetodosPago()
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/api/v1/metodos-pago');
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data'])) {
                    return $data['data'];
                }
                return $data;
            }
            
            \Log::error('Error API metodos-pago: ' . $response->status());
            return [];
        } catch (\Exception $e) {
            \Log::error('Error API metodos-pago: ' . $e->getMessage());
            return [];
        }
    }
}