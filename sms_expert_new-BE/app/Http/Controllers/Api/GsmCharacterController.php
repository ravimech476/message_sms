<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\GsmCharacterConverter;
use Illuminate\Http\Request;

/**
 * GSM Character Conversion API Controller
 * 
 * Provides API endpoints for GSM character analysis and conversion.
 * Used by the frontend to detect and convert Unicode characters to GSM equivalents.
 */
class GsmCharacterController extends Controller
{
    /**
     * Analyze a message for GSM character compatibility
     * 
     * POST /api/gsm/analyze
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);
        
        $message = $request->input('message');
        
        try {
            $analysis = GsmCharacterConverter::analyze($message);
            
            return response()->json([
                'success' => true,
                'data' => $analysis
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to analyze message: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Convert a message to GSM-safe characters
     * 
     * POST /api/gsm/convert
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function convert(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'strict' => 'boolean'
        ]);
        
        $message = $request->input('message');
        $strict = $request->input('strict', false);
        
        try {
            $result = GsmCharacterConverter::convert($message, $strict);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to convert message: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Quick check if a message is GSM-safe
     * 
     * POST /api/gsm/check
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);
        
        $message = $request->input('message');
        
        try {
            $isGsmSafe = GsmCharacterConverter::isGsmSafe($message);
            $nonGsmChars = GsmCharacterConverter::findNonGsmCharacters($message);
            $smsParts = GsmCharacterConverter::calculateSmsParts($message);
            $encoding = $isGsmSafe ? 'GSM' : 'Unicode';
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_gsm_safe' => $isGsmSafe,
                    'encoding' => $encoding,
                    'sms_parts' => $smsParts,
                    'length' => mb_strlen($message),
                    'non_gsm_characters' => $nonGsmChars,
                    'non_gsm_count' => count($nonGsmChars)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to check message: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calculate SMS parts and cost estimate
     * 
     * POST /api/gsm/calculate-parts
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateParts(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);
        
        $message = $request->input('message');
        
        try {
            // Get current encoding stats
            $isGsmSafe = GsmCharacterConverter::isGsmSafe($message);
            $currentParts = GsmCharacterConverter::calculateSmsParts($message, !$isGsmSafe);
            $currentEncoding = $isGsmSafe ? 'GSM' : 'Unicode';
            $currentLength = mb_strlen($message);
            
            // Calculate what it would be after conversion
            $converted = GsmCharacterConverter::convert($message, false);
            $convertedParts = GsmCharacterConverter::calculateSmsParts($converted['message'], false);
            $convertedLength = mb_strlen($converted['message']);
            
            // Characters per part based on encoding
            $currentCharsPerPart = $isGsmSafe ? ($currentParts > 1 ? 153 : 160) : ($currentParts > 1 ? 67 : 70);
            $convertedCharsPerPart = $convertedParts > 1 ? 153 : 160;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'current' => [
                        'encoding' => $currentEncoding,
                        'parts' => $currentParts,
                        'length' => $currentLength,
                        'chars_per_part' => $currentCharsPerPart,
                        'chars_remaining' => ($currentCharsPerPart * $currentParts) - $currentLength
                    ],
                    'after_conversion' => [
                        'encoding' => 'GSM',
                        'parts' => $convertedParts,
                        'length' => $convertedLength,
                        'chars_per_part' => $convertedCharsPerPart,
                        'chars_remaining' => ($convertedCharsPerPart * $convertedParts) - $convertedLength
                    ],
                    'savings' => [
                        'parts_saved' => $currentParts - $convertedParts,
                        'can_save' => $currentParts > $convertedParts,
                        'characters_to_convert' => count($converted['converted'] ?? [])
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to calculate parts: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get the conversion map and character sets
     * 
     * GET /api/gsm/conversion-map
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConversionMap()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'conversion_map' => GsmCharacterConverter::getConversionMap(),
                    'gsm_basic_charset' => GsmCharacterConverter::getGsmBasicCharset(),
                    'gsm_extended_charset' => GsmCharacterConverter::getGsmExtendedCharset()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get conversion map: ' . $e->getMessage()
            ], 500);
        }
    }
}
