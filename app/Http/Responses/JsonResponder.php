<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class JsonResponder
{
    /**
     * Return a success JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    public static function success($data = null, $message = null, $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        ob_start(); // Start output buffering to suppress warnings
        $response = response()->json($response, $status);
        ob_end_clean(); // Clean (erase) the output buffer and turn off output buffering
        return $response;
    }

    /**
     * Return an error JSON response.
     *
     * @param string|null $message
     * @param mixed $errors
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    public static function error($message = null, $errors = null, $status = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];
        ob_start(); // Start output buffering to suppress warnings
        $response = response()->json($response, $status);
        ob_end_clean(); // Clean (erase) the output buffer and turn off output buffering
        return $response;
    }
}
