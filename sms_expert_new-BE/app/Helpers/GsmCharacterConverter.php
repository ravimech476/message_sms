<?php

namespace App\Helpers;

/**
 * GSM Character Converter
 * 
 * Converts Unicode characters to GSM 7-bit compatible characters.
 * This prevents messages from being sent as UCS2 encoding which
 * reduces the character limit from 160 to 70 characters per SMS.
 * 
 * Common problematic characters:
 * - Smart/curly quotes from Word: " " ' ' → " " ' '
 * - Em/En dashes: — – → -
 * - Ellipsis: … → ...
 * - Various Unicode spaces and symbols
 * 
 * @author SMS Expert Team
 * @version 1.0.0
 */
class GsmCharacterConverter
{
    /**
     * OLD SYSTEM VALID_GSM_CHARACTER_SET (shared_lib/sms_constants.inc) — the basic GSM 03.38
     * alphabet. The control-panel send form rejects any character NOT in this set before sending
     * (cp2_sendsms.inc charPositionOrMinusOne), e.g. ^ { } [ ] | ~ € and smart quotes, which are
     * extended-GSM / Word characters that fail on the network. Newline (\n) and CR (\r) are allowed
     * even though they are not listed here (they are handled specially, matching OLD). UTF-8.
     */
    public const VALID_GSM_CHARACTER_SET = '@£$¥èéùìòÇØøÅå ÆæßÉ!"#¤%&\'()*+,-./\\0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà_';

    /**
     * Server-side twin of OLD's charPositionOrMinusOne(). Returns the 1-based position of the
     * first character not allowed in a GSM message, or -1 if the whole text is valid. Newline and
     * carriage return are always allowed. Iterates by Unicode character (not byte).
     */
    public static function firstInvalidCharPosition(string $text): int
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $i => $ch) {
            if ($ch === "\n" || $ch === "\r") {
                continue;
            }
            if (mb_strpos(self::VALID_GSM_CHARACTER_SET, $ch) === false) {
                return $i + 1; // 1-based, matches OLD alert wording
            }
        }
        return -1;
    }

    /**
     * GSM 7-bit Basic Character Set (GSM 03.38)
     * These are the only characters that can be sent in a standard SMS
     * without triggering Unicode/UCS2 encoding.
     */
    private static $gsmBasicCharset = [
        '@', "\xC2\xA3", '$', "\xC2\xA5", "\xC3\xA8", "\xC3\xA9", "\xC3\xB9", "\xC3\xAC", "\xC3\xB2", "\xC3\x87", "\n", "\xC3\x98", "\xC3\xB8", "\r", "\xC3\x85", "\xC3\xA5",
        "\xCE\x94", '_', "\xCE\xA6", "\xCE\x93", "\xCE\x9B", "\xCE\xA9", "\xCE\xA0", "\xCE\xA8", "\xCE\xA3", "\xCE\x98", "\xCE\x9E", "\x1B", "\xC3\x86", "\xC3\xA6", "\xC3\x9F", "\xC3\x89",
        ' ', '!', '"', '#', "\xC2\xA4", '%', '&', "'", '(', ')', '*', '+', ',', '-', '.', '/',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?',
        "\xC2\xA1", 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
        'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', "\xC3\x84", "\xC3\x96", "\xC3\x91", "\xC3\x9C", "\xC2\xA7",
        "\xC2\xBF", 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o',
        'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', "\xC3\xA4", "\xC3\xB6", "\xC3\xB1", "\xC3\xBC", "\xC3\xA0",
    ];

    /**
     * GSM Extended Character Set (requires escape character)
     * These count as 2 characters in an SMS
     */
    private static $gsmExtendedCharset = [
        '^', '{', '}', '\\', '[', '~', ']', '|', "\xE2\x82\xAC", // Euro sign
    ];

    /**
     * Encode a UTF-8 (or Latin-1) message into GSM 03.38 default-alphabet bytes (unpacked),
     * mirroring OLD SYSTEM GsmEncoder::utf8_to_gsm0338 (gsmencoder.class.php).
     *
     * Every GSM basic char maps to its position (£ -> 0x01, @ -> 0x00, $ -> 0x02, _ -> 0x11,
     * accented/Greek letters -> their slots); extension chars (^ { } \ [ ~ ] | €) map to the
     * 0x1B escape + position. This is why a "£" message must NOT be shipped as UCS2 — £ is a
     * first-class GSM character. Chars with no GSM representation become '?' (OLD behaviour)
     * and set fully_encodable=false so the caller can choose UCS2 for genuine Unicode instead.
     *
     * @param string $message
     * @return array{bytes:string, fully_encodable:bool}
     */
    public static function encodeToGsm7bit(string $message): array
    {
        // Normalise to UTF-8 first — the message may arrive Latin-1 encoded (e.g. £ = 0xA3).
        if (!mb_check_encoding($message, 'UTF-8')) {
            $message = mb_convert_encoding($message, 'UTF-8', 'Windows-1252');
        }

        // Reverse map from the canonical GSM basic charset: UTF-8 char => GSM position (0..127).
        static $basic = null;
        if ($basic === null) {
            $basic = array_flip(self::$gsmBasicCharset);
        }
        // GSM 03.38 extension-table positions (sent as 0x1B + position).
        static $extPos = [
            '^' => 0x14, '{' => 0x28, '}' => 0x29, '\\' => 0x2F,
            '[' => 0x3C, '~' => 0x3D, ']' => 0x3E, '|' => 0x40,
            "\xE2\x82\xAC" => 0x65, // €
        ];

        $bytes = '';
        $fullyEncodable = true;
        foreach (preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if (isset($basic[$ch])) {
                $bytes .= chr($basic[$ch]);
            } elseif (isset($extPos[$ch])) {
                $bytes .= "\x1B" . chr($extPos[$ch]);
            } else {
                $bytes .= '?';            // OLD SYSTEM: non-GSM char -> '?'
                $fullyEncodable = false;
            }
        }

        return ['bytes' => $bytes, 'fully_encodable' => $fullyEncodable];
    }

    /**
     * Get Unicode to GSM conversion map
     * Uses UTF-8 byte sequences to avoid PHP parsing issues
     * 
     * @return array
     */
    private static function getUnicodeToGsmMap()
    {
        return [
            // Smart/Curly Quotes (most common issue from Word/rich text)
            "\xE2\x80\x9C" => '"',      // U+201C Left Double Quotation Mark
            "\xE2\x80\x9D" => '"',      // U+201D Right Double Quotation Mark
            "\xE2\x80\x9E" => '"',      // U+201E Double Low-9 Quotation Mark
            "\xE2\x80\x9F" => '"',      // U+201F Double High-Reversed-9 Quotation Mark
            "\xC2\xAB" => '"',          // U+00AB Left-Pointing Double Angle Quotation
            "\xC2\xBB" => '"',          // U+00BB Right-Pointing Double Angle Quotation
            
            // Smart/Curly Apostrophes and Single Quotes
            "\xE2\x80\x98" => "'",      // U+2018 Left Single Quotation Mark
            "\xE2\x80\x99" => "'",      // U+2019 Right Single Quotation Mark (most common!)
            "\xE2\x80\x9A" => "'",      // U+201A Single Low-9 Quotation Mark
            "\xE2\x80\x9B" => "'",      // U+201B Single High-Reversed-9 Quotation Mark
            "\xE2\x80\xB9" => "'",      // U+2039 Single Left-Pointing Angle Quotation
            "\xE2\x80\xBA" => "'",      // U+203A Single Right-Pointing Angle Quotation
            "\x60" => "'",              // U+0060 Grave Accent (backtick)
            "\xC2\xB4" => "'",          // U+00B4 Acute Accent
            
            // Dashes and Hyphens
            "\xE2\x80\x94" => '-',      // U+2014 Em Dash
            "\xE2\x80\x93" => '-',      // U+2013 En Dash
            "\xE2\x80\x90" => '-',      // U+2010 Hyphen
            "\xE2\x80\x91" => '-',      // U+2011 Non-Breaking Hyphen
            "\xE2\x80\x92" => '-',      // U+2012 Figure Dash
            "\xE2\x80\x95" => '-',      // U+2015 Horizontal Bar
            "\xE2\x88\x92" => '-',      // U+2212 Minus Sign
            "\xE2\x81\x83" => '-',      // U+2043 Hyphen Bullet
            
            // Ellipsis
            "\xE2\x80\xA6" => '...',    // U+2026 Horizontal Ellipsis
            "\xE2\x8B\xAF" => '...',    // U+22EF Midline Horizontal Ellipsis
            
            // Spaces (various Unicode spaces)
            "\xC2\xA0" => ' ',          // U+00A0 No-Break Space
            "\xE2\x80\x80" => ' ',      // U+2000 En Quad
            "\xE2\x80\x81" => ' ',      // U+2001 Em Quad
            "\xE2\x80\x82" => ' ',      // U+2002 En Space
            "\xE2\x80\x83" => ' ',      // U+2003 Em Space
            "\xE2\x80\x84" => ' ',      // U+2004 Three-Per-Em Space
            "\xE2\x80\x85" => ' ',      // U+2005 Four-Per-Em Space
            "\xE2\x80\x86" => ' ',      // U+2006 Six-Per-Em Space
            "\xE2\x80\x87" => ' ',      // U+2007 Figure Space
            "\xE2\x80\x88" => ' ',      // U+2008 Punctuation Space
            "\xE2\x80\x89" => ' ',      // U+2009 Thin Space
            "\xE2\x80\x8A" => ' ',      // U+200A Hair Space
            "\xE2\x80\x8B" => '',       // U+200B Zero Width Space (remove)
            "\xE2\x80\xAF" => ' ',      // U+202F Narrow No-Break Space
            "\xE2\x81\x9F" => ' ',      // U+205F Medium Mathematical Space
            "\xE3\x80\x80" => ' ',      // U+3000 Ideographic Space
            
            // Bullets and List Markers
            "\xE2\x80\xA2" => '*',      // U+2022 Bullet
            "\xE2\x97\xA6" => '*',      // U+25E6 White Bullet
            "\xE2\x96\xAA" => '*',      // U+25AA Black Small Square
            "\xE2\x96\xAB" => '*',      // U+25AB White Small Square
            "\xE2\x97\x8F" => '*',      // U+25CF Black Circle
            "\xE2\x97\x8B" => '*',      // U+25CB White Circle
            "\xE2\x96\xA0" => '*',      // U+25A0 Black Square
            "\xE2\x96\xA1" => '*',      // U+25A1 White Square
            "\xE2\x97\x86" => '*',      // U+25C6 Black Diamond
            "\xE2\x97\x87" => '*',      // U+25C7 White Diamond
            "\xE2\x98\x85" => '*',      // U+2605 Black Star
            "\xE2\x98\x86" => '*',      // U+2606 White Star
            "\xE2\x96\xBA" => '>',      // U+25BA Black Right-Pointing Pointer
            "\xE2\x96\xB6" => '>',      // U+25B6 Black Right-Pointing Triangle
            "\xE2\x97\x84" => '<',      // U+25C4 Black Left-Pointing Pointer
            "\xE2\x97\x80" => '<',      // U+25C0 Black Left-Pointing Triangle
            
            // Arrows
            "\xE2\x86\x92" => '->',     // U+2192 Rightwards Arrow
            "\xE2\x86\x90" => '<-',     // U+2190 Leftwards Arrow
            "\xE2\x86\x91" => '^',      // U+2191 Upwards Arrow
            "\xE2\x86\x93" => 'v',      // U+2193 Downwards Arrow
            "\xE2\x87\x92" => '=>',     // U+21D2 Rightwards Double Arrow
            "\xE2\x87\x90" => '<=',     // U+21D0 Leftwards Double Arrow
            
            // Mathematical and Special Symbols
            "\xC3\x97" => 'x',          // U+00D7 Multiplication Sign
            "\xC3\xB7" => '/',          // U+00F7 Division Sign
            "\xC2\xB1" => '+/-',        // U+00B1 Plus-Minus Sign
            "\xE2\x89\xA0" => '!=',     // U+2260 Not Equal To
            "\xE2\x89\xA4" => '<=',     // U+2264 Less-Than or Equal To
            "\xE2\x89\xA5" => '>=',     // U+2265 Greater-Than or Equal To
            "\xE2\x89\x88" => '~=',     // U+2248 Almost Equal To
            "\xE2\x88\x9E" => 'inf',    // U+221E Infinity
            "\xC2\xB0" => 'deg',        // U+00B0 Degree Sign
            "\xE2\x84\xA2" => '(TM)',   // U+2122 Trade Mark Sign
            "\xC2\xA9" => '(C)',        // U+00A9 Copyright Sign
            "\xC2\xAE" => '(R)',        // U+00AE Registered Sign
            "\xE2\x80\xA0" => '+',      // U+2020 Dagger
            "\xE2\x80\xA1" => '++',     // U+2021 Double Dagger
            "\xC2\xB6" => 'P',          // U+00B6 Pilcrow Sign
            
            // Fractions
            "\xC2\xBD" => '1/2',        // U+00BD Vulgar Fraction One Half
            "\xC2\xBC" => '1/4',        // U+00BC Vulgar Fraction One Quarter
            "\xC2\xBE" => '3/4',        // U+00BE Vulgar Fraction Three Quarters
            "\xE2\x85\x93" => '1/3',    // U+2153 Vulgar Fraction One Third
            "\xE2\x85\x94" => '2/3',    // U+2154 Vulgar Fraction Two Thirds
            
            // Currency (non-GSM currencies)
            "\xC2\xA2" => 'c',          // U+00A2 Cent Sign
            "\xE2\x82\xA4" => 'L',      // U+20A4 Lira Sign
            "\xE2\x82\xB1" => 'P',      // U+20B1 Peso Sign
            "\xE2\x82\xB9" => 'Rs',     // U+20B9 Indian Rupee Sign
            "\xE2\x82\xBD" => 'RUB',    // U+20BD Russian Ruble Sign
            "\xE0\xB8\xBF" => 'B',      // U+0E3F Thai Baht Sign
            "\xE2\x82\xBF" => 'BTC',    // U+20BF Bitcoin Sign
            
            // Common Accented Characters (convert to base letter)
            "\xC3\xA1" => 'a', "\xC3\xA2" => 'a', "\xC3\xA3" => 'a', "\xC4\x81" => 'a', "\xC4\x83" => 'a', "\xC4\x85" => 'a',
            "\xC4\x87" => 'c', "\xC4\x89" => 'c', "\xC4\x8D" => 'c', "\xC4\x8B" => 'c',
            "\xC4\x8F" => 'd', "\xC4\x91" => 'd',
            "\xC4\x93" => 'e', "\xC4\x95" => 'e', "\xC4\x97" => 'e', "\xC4\x99" => 'e', "\xC4\x9B" => 'e', "\xC3\xAA" => 'e', "\xC3\xAB" => 'e',
            "\xC4\x9D" => 'g', "\xC4\x9F" => 'g', "\xC4\xA1" => 'g', "\xC4\xA3" => 'g',
            "\xC4\xA5" => 'h', "\xC4\xA7" => 'h',
            "\xC4\xA9" => 'i', "\xC4\xAB" => 'i', "\xC4\xAD" => 'i', "\xC4\xAF" => 'i', "\xC4\xB1" => 'i', "\xC3\xAE" => 'i', "\xC3\xAF" => 'i', "\xC3\xAD" => 'i',
            "\xC4\xB5" => 'j',
            "\xC4\xB7" => 'k',
            "\xC4\xBA" => 'l', "\xC4\xBC" => 'l', "\xC4\xBE" => 'l', "\xC5\x80" => 'l', "\xC5\x82" => 'l',
            "\xC5\x84" => 'n', "\xC5\x86" => 'n', "\xC5\x88" => 'n', "\xC5\x89" => 'n', "\xC5\x8B" => 'n',
            "\xC5\x8D" => 'o', "\xC5\x8F" => 'o', "\xC5\x91" => 'o', "\xC5\x93" => 'oe', "\xC3\xB4" => 'o', "\xC3\xB5" => 'o', "\xC3\xB3" => 'o',
            "\xC5\x95" => 'r', "\xC5\x97" => 'r', "\xC5\x99" => 'r',
            "\xC5\x9B" => 's', "\xC5\x9D" => 's', "\xC5\x9F" => 's', "\xC5\xA1" => 's',
            "\xC5\xA3" => 't', "\xC5\xA5" => 't', "\xC5\xA7" => 't',
            "\xC5\xA9" => 'u', "\xC5\xAB" => 'u', "\xC5\xAD" => 'u', "\xC5\xAF" => 'u', "\xC5\xB1" => 'u', "\xC5\xB3" => 'u', "\xC3\xBB" => 'u', "\xC3\xBA" => 'u',
            "\xC5\xB5" => 'w',
            "\xC5\xB7" => 'y', "\xC3\xBF" => 'y', "\xC3\xBD" => 'y',
            "\xC5\xBA" => 'z', "\xC5\xBC" => 'z', "\xC5\xBE" => 'z',
            
            // Uppercase accented (convert to base letter)
            "\xC3\x81" => 'A', "\xC3\x82" => 'A', "\xC3\x83" => 'A', "\xC4\x80" => 'A', "\xC4\x82" => 'A', "\xC4\x84" => 'A',
            "\xC4\x86" => 'C', "\xC4\x88" => 'C', "\xC4\x8C" => 'C', "\xC4\x8A" => 'C',
            "\xC4\x8E" => 'D', "\xC4\x90" => 'D',
            "\xC4\x92" => 'E', "\xC4\x94" => 'E', "\xC4\x96" => 'E', "\xC4\x98" => 'E', "\xC4\x9A" => 'E', "\xC3\x8A" => 'E', "\xC3\x8B" => 'E', "\xC3\x88" => 'E',
            "\xC4\x9C" => 'G', "\xC4\x9E" => 'G', "\xC4\xA0" => 'G', "\xC4\xA2" => 'G',
            "\xC4\xA4" => 'H', "\xC4\xA6" => 'H',
            "\xC4\xA8" => 'I', "\xC4\xAA" => 'I', "\xC4\xAC" => 'I', "\xC4\xAE" => 'I', "\xC4\xB0" => 'I', "\xC3\x8E" => 'I', "\xC3\x8F" => 'I', "\xC3\x8D" => 'I', "\xC3\x8C" => 'I',
            "\xC4\xB4" => 'J',
            "\xC4\xB6" => 'K',
            "\xC4\xB9" => 'L', "\xC4\xBB" => 'L', "\xC4\xBD" => 'L', "\xC4\xBF" => 'L', "\xC5\x81" => 'L',
            "\xC5\x83" => 'N', "\xC5\x85" => 'N', "\xC5\x87" => 'N', "\xC5\x8A" => 'N',
            "\xC5\x8C" => 'O', "\xC5\x8E" => 'O', "\xC5\x90" => 'O', "\xC5\x92" => 'OE', "\xC3\x94" => 'O', "\xC3\x95" => 'O', "\xC3\x93" => 'O', "\xC3\x92" => 'O',
            "\xC5\x94" => 'R', "\xC5\x96" => 'R', "\xC5\x98" => 'R',
            "\xC5\x9A" => 'S', "\xC5\x9C" => 'S', "\xC5\x9E" => 'S', "\xC5\xA0" => 'S',
            "\xC5\xA2" => 'T', "\xC5\xA4" => 'T', "\xC5\xA6" => 'T',
            "\xC5\xA8" => 'U', "\xC5\xAA" => 'U', "\xC5\xAC" => 'U', "\xC5\xAE" => 'U', "\xC5\xB0" => 'U', "\xC5\xB2" => 'U', "\xC3\x9B" => 'U', "\xC3\x9A" => 'U', "\xC3\x99" => 'U',
            "\xC5\xB4" => 'W',
            "\xC5\xB6" => 'Y', "\xC5\xB8" => 'Y', "\xC3\x9D" => 'Y',
            "\xC5\xB9" => 'Z', "\xC5\xBB" => 'Z', "\xC5\xBD" => 'Z',
            
            // Line breaks and formatting
            "\r\n" => "\n",  // Windows line ending
            "\r" => "\n",    // Old Mac line ending
            "\t" => ' ',     // Tab to space
            
            // Other common substitutions
            "\xC2\xB7" => '.',          // U+00B7 Middle Dot
            "\xE2\x80\xA7" => '.',      // U+2027 Hyphenation Point
            "\xE2\x80\xB2" => "'",      // U+2032 Prime
            "\xE2\x80\xB3" => '"',      // U+2033 Double Prime
            "\xE2\x80\xB4" => '"',      // U+2034 Triple Prime
            "\xE2\x81\x84" => '/',      // U+2044 Fraction Slash
            "\xE2\x88\x95" => '/',      // U+2215 Division Slash
        ];
    }

    /**
     * Get character descriptions for UI feedback
     * 
     * @return array
     */
    private static function getCharDescriptions()
    {
        return [
            "\xE2\x80\x9C" => 'Left Double Quotation Mark (Word smart quote)',
            "\xE2\x80\x9D" => 'Right Double Quotation Mark (Word smart quote)',
            "\xE2\x80\x98" => 'Left Single Quotation Mark (Word smart apostrophe)',
            "\xE2\x80\x99" => 'Right Single Quotation Mark (Word smart apostrophe)',
            "\xE2\x80\x94" => 'Em Dash (long dash)',
            "\xE2\x80\x93" => 'En Dash (medium dash)',
            "\xE2\x80\xA6" => 'Horizontal Ellipsis',
            "\xE2\x80\xA2" => 'Bullet Point',
            "\xE2\x84\xA2" => 'Trademark Symbol',
            "\xC2\xA9" => 'Copyright Symbol',
            "\xC2\xAE" => 'Registered Trademark Symbol',
            "\xC2\xB0" => 'Degree Symbol',
            "\xE2\x82\xAC" => 'Euro Sign',
            "\xC2\xA3" => 'Pound Sign',
            "\xC2\xA5" => 'Yen Sign',
            "\xC2\xA0" => 'Non-Breaking Space',
        ];
    }

    /**
     * Convert a message to GSM-safe characters
     * 
     * @param string $message The original message
     * @param bool $strict If true, removes characters that can't be converted
     * @return array Contains 'message' (converted), 'converted' (list of conversions), 'removed' (list of removed chars)
     */
    public static function convert(string $message, bool $strict = false): array
    {
        $converted = [];
        $removed = [];
        $originalMessage = $message;
        $unicodeToGsmMap = self::getUnicodeToGsmMap();
        
        // Apply all conversions from the map
        foreach ($unicodeToGsmMap as $unicode => $gsm) {
            if (mb_strpos($message, $unicode) !== false) {
                $count = mb_substr_count($message, $unicode);
                $converted[] = [
                    'from' => $unicode,
                    'to' => $gsm,
                    'count' => $count,
                    'from_hex' => self::getHexCodes($unicode),
                    'description' => self::getCharacterDescription($unicode)
                ];
                $message = str_replace($unicode, $gsm, $message);
            }
        }
        
        // Check for any remaining non-GSM characters
        $remainingNonGsm = self::findNonGsmCharacters($message);
        
        if (!empty($remainingNonGsm) && $strict) {
            foreach ($remainingNonGsm as $char) {
                $removed[] = [
                    'char' => $char,
                    'hex' => self::getHexCodes($char),
                    'description' => self::getCharacterDescription($char)
                ];
                $message = str_replace($char, '', $message);
            }
        }
        
        return [
            'message' => $message,
            'original' => $originalMessage,
            'converted' => $converted,
            'removed' => $removed,
            'remaining_non_gsm' => $remainingNonGsm,
            'is_gsm_safe' => empty($remainingNonGsm),
            'original_encoding' => self::detectEncoding($originalMessage),
            'converted_encoding' => self::detectEncoding($message),
            'original_length' => mb_strlen($originalMessage),
            'converted_length' => mb_strlen($message),
            'gsm_parts' => self::calculateSmsParts($message, false),
            'unicode_parts' => self::calculateSmsParts($originalMessage, true),
            'cost_savings' => self::calculateCostSavings($originalMessage, $message)
        ];
    }

    /**
     * Quick convert without detailed reporting
     * 
     * @param string $message The original message
     * @return string The converted message
     */
    public static function quickConvert(string $message): string
    {
        $unicodeToGsmMap = self::getUnicodeToGsmMap();
        foreach ($unicodeToGsmMap as $unicode => $gsm) {
            $message = str_replace($unicode, $gsm, $message);
        }
        return $message;
    }

    /**
     * Check if a message is GSM-safe (no Unicode characters)
     * 
     * @param string $message The message to check
     * @return bool True if message only contains GSM characters
     */
    public static function isGsmSafe(string $message): bool
    {
        return empty(self::findNonGsmCharacters($message));
    }

    /**
     * Find all non-GSM characters in a message
     * 
     * @param string $message The message to check
     * @return array List of non-GSM characters found
     */
    public static function findNonGsmCharacters(string $message): array
    {
        $nonGsm = [];
        $allGsmChars = array_merge(self::$gsmBasicCharset, self::$gsmExtendedCharset);
        
        // Convert message to array of characters (UTF-8 aware)
        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($chars as $char) {
            if (!in_array($char, $allGsmChars) && !in_array($char, $nonGsm)) {
                $nonGsm[] = $char;
            }
        }
        
        return $nonGsm;
    }

    /**
     * Get detailed analysis of a message
     * 
     * @param string $message The message to analyze
     * @return array Detailed analysis including character breakdown
     */
    public static function analyze(string $message): array
    {
        $analysis = [
            'message' => $message,
            'length' => mb_strlen($message),
            'encoding' => self::detectEncoding($message),
            'is_gsm_safe' => self::isGsmSafe($message),
            'gsm_characters' => [],
            'extended_characters' => [],
            'unicode_characters' => [],
            'convertible_characters' => [],
            'non_convertible_characters' => [],
            'sms_parts_if_gsm' => 0,
            'sms_parts_if_unicode' => 0,
        ];
        
        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);
        $unicodeToGsmMap = self::getUnicodeToGsmMap();
        
        foreach ($chars as $char) {
            if (in_array($char, self::$gsmBasicCharset)) {
                $analysis['gsm_characters'][] = $char;
            } elseif (in_array($char, self::$gsmExtendedCharset)) {
                $analysis['extended_characters'][] = $char;
            } else {
                $analysis['unicode_characters'][] = [
                    'char' => $char,
                    'hex' => self::getHexCodes($char),
                    'description' => self::getCharacterDescription($char)
                ];
                
                if (isset($unicodeToGsmMap[$char])) {
                    $analysis['convertible_characters'][] = [
                        'from' => $char,
                        'to' => $unicodeToGsmMap[$char],
                        'description' => self::getCharacterDescription($char)
                    ];
                } else {
                    $analysis['non_convertible_characters'][] = [
                        'char' => $char,
                        'hex' => self::getHexCodes($char),
                        'description' => self::getCharacterDescription($char)
                    ];
                }
            }
        }
        
        $analysis['sms_parts_if_gsm'] = self::calculateSmsParts($message, false);
        $analysis['sms_parts_if_unicode'] = self::calculateSmsParts($message, true);
        
        return $analysis;
    }

    /**
     * Calculate the number of SMS parts needed
     * 
     * @param string $message The message
     * @param bool $forceUnicode Force Unicode calculation
     * @return int Number of SMS parts
     */
    public static function calculateSmsParts(string $message, bool $forceUnicode = false): int
    {
        $length = mb_strlen($message);
        
        if ($length === 0) {
            return 0;
        }
        
        // Check if message requires Unicode
        $requiresUnicode = $forceUnicode || !self::isGsmSafe($message);
        
        if ($requiresUnicode) {
            // UCS-2 encoding: 70 chars for single, 67 for multipart
            if ($length <= 70) {
                return 1;
            }
            return (int) ceil($length / 67);
        } else {
            // GSM 7-bit encoding: 160 chars for single, 153 for multipart
            // Count extended characters (they use 2 chars worth)
            $extendedCount = 0;
            $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($chars as $char) {
                if (in_array($char, self::$gsmExtendedCharset)) {
                    $extendedCount++;
                }
            }
            
            $effectiveLength = $length + $extendedCount; // Extended chars count as 2
            
            if ($effectiveLength <= 160) {
                return 1;
            }
            return (int) ceil($effectiveLength / 153);
        }
    }

    /**
     * Calculate cost savings from conversion
     * 
     * @param string $original Original message
     * @param string $converted Converted message
     * @return array Cost savings information
     */
    private static function calculateCostSavings(string $original, string $converted): array
    {
        $originalParts = self::calculateSmsParts($original, !self::isGsmSafe($original));
        $convertedParts = self::calculateSmsParts($converted, !self::isGsmSafe($converted));
        
        $partsSaved = $originalParts - $convertedParts;
        $percentageSaved = $originalParts > 0 ? round(($partsSaved / $originalParts) * 100, 1) : 0;
        
        return [
            'original_parts' => $originalParts,
            'converted_parts' => $convertedParts,
            'parts_saved' => $partsSaved,
            'percentage_saved' => $percentageSaved,
            'encoding_changed' => !self::isGsmSafe($original) && self::isGsmSafe($converted)
        ];
    }

    /**
     * Detect the encoding type needed for a message
     * 
     * @param string $message The message to check
     * @return string 'GSM' or 'Unicode'
     */
    public static function detectEncoding(string $message): string
    {
        return self::isGsmSafe($message) ? 'GSM' : 'Unicode';
    }

    /**
     * Get hex codes for a character
     * 
     * @param string $char The character
     * @return string Hex representation
     */
    private static function getHexCodes(string $char): string
    {
        $hex = '';
        for ($i = 0; $i < strlen($char); $i++) {
            $hex .= sprintf('%02X', ord($char[$i]));
        }
        return 'U+' . $hex;
    }

    /**
     * Get description of a character
     * 
     * @param string $char The character
     * @return string Description
     */
    private static function getCharacterDescription(string $char): string
    {
        $descriptions = self::getCharDescriptions();
        return $descriptions[$char] ?? 'Unicode Character';
    }

    /**
     * Get the complete conversion map (for frontend use)
     * 
     * @return array The conversion map
     */
    public static function getConversionMap(): array
    {
        return self::getUnicodeToGsmMap();
    }

    /**
     * Get the GSM basic character set
     * 
     * @return array The GSM basic charset
     */
    public static function getGsmBasicCharset(): array
    {
        return self::$gsmBasicCharset;
    }

    /**
     * Get the GSM extended character set
     * 
     * @return array The GSM extended charset
     */
    public static function getGsmExtendedCharset(): array
    {
        return self::$gsmExtendedCharset;
    }
}
