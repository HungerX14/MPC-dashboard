<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when WordPress API communication fails
 */
class WordpressApiException extends \RuntimeException
{
    public const TIMEOUT = 1;
    public const INVALID_TOKEN = 2;
    public const CONNECTION_ERROR = 3;
    public const INVALID_RESPONSE = 4;
    public const ENDPOINT_NOT_FOUND = 5;
    public const ACCESS_FORBIDDEN = 6;
    public const SERVER_ERROR = 7;
    public const HTTP_ERROR = 8;
    public const UNKNOWN_ERROR = 99;

    /**
     * Get a user-friendly error message based on the error code
     */
    public function getUserMessage(): string
    {
        return match ($this->code) {
            self::TIMEOUT => 'Le site WordPress ne repond pas. Verifiez que le site est accessible.',
            self::INVALID_TOKEN => 'Le token API est invalide ou expire. Verifiez la configuration.',
            self::CONNECTION_ERROR => 'Impossible de se connecter au site WordPress. Verifiez l\'URL.',
            self::INVALID_RESPONSE => 'Reponse invalide du site WordPress. Le plugin est-il installe ?',
            self::ENDPOINT_NOT_FOUND => 'L\'endpoint API n\'existe pas. Verifiez que le plugin est active.',
            self::ACCESS_FORBIDDEN => 'Acces refuse. Verifiez les permissions du token.',
            self::SERVER_ERROR => 'Erreur serveur WordPress. Contactez l\'administrateur du site.',
            self::HTTP_ERROR => 'Erreur de communication avec le site WordPress.',
            default => 'Une erreur inconnue est survenue.',
        };
    }

    /**
     * Check if the error is retryable
     */
    public function isRetryable(): bool
    {
        return in_array($this->code, [
            self::TIMEOUT,
            self::CONNECTION_ERROR,
            self::SERVER_ERROR,
        ], true);
    }
}
