<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReceiptHandlerController extends Controller
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => 'true',
            'data' => $request->all()
        ]);
    }
}
