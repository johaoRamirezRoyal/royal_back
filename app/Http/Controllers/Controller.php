<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function success(string $message, mixed $data = null, int $status = 200)
    {
        return response()->json(
            [
                'message' => $message,
                'success' => true,
                'data' => $data,
            ],
            $status,
            [],
            JSON_INVALID_UTF8_SUBSTITUTE
        )
            ->header('Accept', 'application/json');
    }

    protected function paginatedResponse(
        array $response,
        ?string $resourceClass = null
    ): JsonResponse {

        if ($response['error']) {
            return $this->apiResponse($response);
        }

        $paginator = $response['data'];

        if ($resourceClass) {
            $resource = $resourceClass::collection($paginator)
                ->response()
                ->getData(true);

            $data = $resource['data'];
        } else {
            $data = $paginator->items();
        }

        return $this->apiResponse([
            'error' => false,
            'message' => $response['message'],
            'data' => [
                'data' => $data,
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'current_page' => $paginator->currentPage(),
            ],
        ]);
    }

    public function error(
        array|string $message = ['message' => 'ERROR'],
        int $status = 500,
        ?string $file = null,
        ?int $line = null,
        ?int $code = null,
    ): JsonResponse {
        $message = is_array($message) ? $message : [$message];

        return response()->json([
            'error' => true,
            'error_type' => 'logic',
            'error_code' => $code ?? $status,
            'message' => is_array($message) ? implode(', ', $message) : $message,
            'file' => $file,
            'line' => $line,
        ], $status);
    }

    protected function apiResponse(array $response): JsonResponse
    {
        $isError = $response['error'] ?? false;

        if (!$isError) {
            return response()->json($response, 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $message = $response['message'] ?? 'Error interno del servidor';

        $errorType = match (true) {
            preg_match('/SQLSTATE|DriverException|PDO::|Base de datos|database|conexión.*bd|mysql|postgres/i', $message) => 'database',
            preg_match('/Connection|cURL|refused|timeout|conexión|host|network|DNS/i', $message) => 'connection',
            default => 'logic',
        };

        $status = match ($errorType) {
            'database' => 500,
            'connection' => 503,
            default => $response['status'] ?? 400,
        };

        $message = preg_replace('/\(#\d+\)/', '', $message);
        $message = explode("\n", $message)[0];
        $message = strlen($message) > 200 ? substr($message, 0, 200) . '...' : $message;

        return response()->json([
            'error' => true,
            'error_type' => $errorType,
            'error_code' => $response['code'] ?? $status,
            'message' => $message,
            'file' => $response['file'] ?? null,
            'line' => $response['line'] ?? null,
        ], $status);
    }
}
