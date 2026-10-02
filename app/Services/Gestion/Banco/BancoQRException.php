<?php

namespace App\Services\Gestion\Banco;

use Exception;
use Throwable;

class BancoQRException extends Exception
{
    protected ?array $respuestaBanco;
    protected string $tipoError;

    public function __construct(
        string $message = '',
        string $tipoError = 'GENERICO',
        ?array $respuestaBanco = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->tipoError = $tipoError;
        $this->respuestaBanco = $respuestaBanco;
    }

    public function getTipoError(): string { return $this->tipoError; }
    public function getRespuestaBanco(): ?array { return $this->respuestaBanco; }
    public function esErrorDeAutenticacion(): bool { return $this->tipoError === 'AUTENTICACION'; }
    public function esErrorDeConexion(): bool { return $this->tipoError === 'CONEXION'; }
    public function esErrorDeNegocio(): bool { return $this->tipoError === 'NEGOCIO'; }
}