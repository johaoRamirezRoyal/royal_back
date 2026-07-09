<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

abstract class Service
{
    public function sendError(Exception $e, string $message): array
    {
        $error = [
            'error' => true,
            'message' => $message,
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'code' => $e->getCode(),
        ];

        Log::error($message, [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'context' => $e->getPrevious(),
            'code' => $e->getCode(),
        ]);

        return $error;
    }

}