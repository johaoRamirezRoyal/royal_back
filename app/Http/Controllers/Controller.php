<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function success(string $message, mixed $data = null, int $status = 200)
    {
        return response()->json(
            [
                'message' => $message,
                'data' => $data,
            ],
            $status
        )
            ->header('Accept', 'application/json');
    }

    public function error(array|string $message = ['message' => 'ERROR'], int $status = 500)
    {
        return response()->json($message, $status);
    }
}
