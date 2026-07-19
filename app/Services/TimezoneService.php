<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class TimezoneService
{
    /**
     * Obtener la zona horaria del cliente actual
     */
    public function getTimezone(): string
    {
        return Session::get('zona_horaria', Config::get('app.timezone', 'America/La_Paz'));
    }

    /**
     * Obtener la fecha y hora actual en la zona horaria del cliente
     */
    public function now(): Carbon
    {
        return Carbon::now($this->getTimezone());
    }

    /**
     * Obtener la fecha actual en la zona horaria del cliente
     */
    public function today(): string
    {
        return $this->now()->toDateString();
    }

    /**
     * Obtener la fecha y hora actual formateada
     */
    public function getFechaHoraActual(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    /**
     * Obtener la fecha actual formateada
     */
    public function getFechaActual(): string
    {
        return $this->today();
    }

    /**
     * Obtener la hora actual formateada
     */
    public function getHoraActual(): string
    {
        return $this->now()->format('H:i:s');
    }

    /**
     * Convertir una fecha a la zona horaria del cliente
     */
    public function convertToTimezone($date, ?string $fromTimezone = null): ?Carbon
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->copy()->tz($this->getTimezone());
        }

        return Carbon::parse($date, $fromTimezone)->tz($this->getTimezone());
    }

    /**
     * Formatear una fecha para mostrar
     */
    public function formatDate($date, string $format = 'd/m/Y H:i:s'): ?string
    {
        if (!$date) {
            return null;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $carbon->tz($this->getTimezone())->format($format);
    }

    /**
     * Verificar si una fecha es hoy (en la zona horaria del cliente)
     */
    public function isToday($date): bool
    {
        if (!$date) {
            return false;
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $carbon->tz($this->getTimezone())->isToday();
    }

    /**
     * Obtener el inicio del día (en la zona horaria del cliente)
     */
    public function startOfDay(): Carbon
    {
        return $this->now()->startOfDay();
    }

    /**
     * Obtener el fin del día (en la zona horaria del cliente)
     */
    public function endOfDay(): Carbon
    {
        return $this->now()->endOfDay();
    }
}