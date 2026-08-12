<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * SMS Validation Service
 *
 * Provides validation logic matching the OLD SYSTEM (cp2_sendsms.inc)
 * Validates sender IDs, message content, GSM characters, and message length
 */
class SmsValidationService
{
    /**
     * Maximum characters for a single SMS message
     */
    const SINGLE_MESSAGE_LENGTH = 160;

    /**
     * Maximum characters per part for concatenated (multi-part) messages
     */
    const MULTI_MESSAGE_LENGTH = 153;

    /**
     * Maximum number of message parts allowed (9 x 153 = 1377 chars)
     */
    const MAX_MESSAGE_PARTS = 9;

    /**
     * Maximum total characters allowed
     */
    const MAX_TOTAL_LENGTH = 1377; // 9 x 153

    /**
     * GSM 7-bit default alphabet characters
     * These are the only valid characters for standard SMS encoding
     */
    const GSM_CHARS = [
        '@', '£', '$', '¥', 'è', 'é', 'ù', 'ì', 'ò', 'Ç', "\n", 'Ø', 'ø', "\r", 'Å', 'å',
        'Δ', '_', 'Φ', 'Γ', 'Λ', 'Ω', 'Π', 'Ψ', 'Σ', 'Θ', 'Ξ', 'Æ', 'æ', 'ß', 'É',
        ' ', '!', '"', '#', '¤', '%', '&', "'", '(', ')', '*', '+', ',', '-', '.', '/',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?',
        '¡', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
        'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'Ä', 'Ö', 'Ñ', 'Ü', '§',
        '¿', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o',
        'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'ä', 'ö', 'ñ', 'ü', 'à',
        // Extended GSM characters (count as 2 characters each)
        '^', '{', '}', '\\', '[', ']', '~', '|', '€'
    ];

    /**
     * Validate all SMS parameters (like OLD SYSTEM cp2_sendsms.inc)
     *
     * @param string $senderId The sender ID (From field)
     * @param string $message The message content
     * @param array $recipients Array of recipient phone numbers
     * @param float $walletBalance Current wallet balance
     * @param float $totalCost Total cost for sending
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateAll(string $senderId, string $message, array $recipients, float $walletBalance, float $totalCost): array
    {
        $errors = [];

        // 1. Validate sender ID
        $senderValidation = $this->validateSenderId($senderId);
        if (!$senderValidation['valid']) {
            $errors[] = $senderValidation['error'];
        }

        // 2. Validate message content is not empty
        if (empty(trim($message))) {
            $errors[] = 'You must specify a message of some sort to actually send.';
        }

        // 3. Validate recipients are not empty
        if (empty($recipients)) {
            $errors[] = "You haven't specified any recipients to send to.";
        }

        // 4. Validate message length
        $lengthValidation = $this->validateMessageLength($message);
        if (!$lengthValidation['valid']) {
            $errors[] = $lengthValidation['error'];
        }

        // 5. Validate GSM characters
        $gsmValidation = $this->validateGsmCharacters($message);
        if (!$gsmValidation['valid']) {
            $errors[] = $gsmValidation['error'];
        }

        // 6. Validate wallet balance
        if ($walletBalance < $totalCost) {
            $shortage = $totalCost - $walletBalance;
            $errors[] = "Wallet funds too low - you cannot afford to send these messages. Please top up your SMS wallet by at least £" . number_format($shortage, 2) . ".";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate sender ID (From field)
     *
     * OLD SYSTEM rules (cp2_sendsms.inc):
     * - Alphanumeric: A letter followed by up to 10 letters/numbers (max 11 chars total)
     * - Numeric: Up to 15 digits (like 00447889111000 or 07889111000)
     * - Shortcode: 5 digits (like 60300)
     *
     * @param string $senderId
     * @return array ['valid' => bool, 'error' => string|null, 'type' => string|null]
     */
    public function validateSenderId(string $senderId): array
    {
        $senderId = trim($senderId);

        if (empty($senderId)) {
            return [
                'valid' => false,
                'error' => "The Sender ID cannot be empty.",
                'type' => null
            ];
        }

        // Check for shortcode (exactly 5 digits)
        if (preg_match('/^\d{5}$/', $senderId)) {
            return [
                'valid' => true,
                'error' => null,
                'type' => 'shortcode'
            ];
        }

        // Check for numeric (up to 15 digits, mobile number format)
        if (preg_match('/^\+?\d{1,15}$/', $senderId)) {
            // Remove + for length check
            $numericOnly = preg_replace('/[^0-9]/', '', $senderId);
            if (strlen($numericOnly) <= 15) {
                return [
                    'valid' => true,
                    'error' => null,
                    'type' => 'msisdn'
                ];
            }
        }

        // Check for alphanumeric (starts with letter, max 11 chars, letters and numbers only)
        if (preg_match('/^[A-Za-z][A-Za-z0-9]{0,10}$/', $senderId)) {
            return [
                'valid' => true,
                'error' => null,
                'type' => 'alpha'
            ];
        }

        return [
            'valid' => false,
            'error' => "The Sender ID (Text message 'From' value) is not valid. This must be either: 1) a shortcode (5 digits, like 60300), 2) alphanumeric (a letter followed by up to 10 letters and numbers, like MyLinks365), or 3) a mobile number, up to 15 digits, like 00447889111000 or 07889111000.",
            'type' => null
        ];
    }

    /**
     * Validate message length
     *
     * OLD SYSTEM rules:
     * - Single message: max 160 characters
     * - Multi-part messages: max 9 parts x 153 chars = 1377 characters
     *
     * @param string $message
     * @return array ['valid' => bool, 'error' => string|null, 'parts' => int, 'length' => int]
     */
    public function validateMessageLength(string $message): array
    {
        $length = mb_strlen($message);
        $parts = $this->calculateMessageParts($message);

        if ($length > self::MAX_TOTAL_LENGTH) {
            return [
                'valid' => false,
                'error' => "You cannot send a message with more than " . self::MAX_TOTAL_LENGTH . " characters. Your message has {$length} characters.",
                'parts' => $parts,
                'length' => $length
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'parts' => $parts,
            'length' => $length
        ];
    }

    /**
     * Calculate number of SMS parts for a message
     *
     * @param string $message
     * @return int Number of SMS parts
     */
    public function calculateMessageParts(string $message): int
    {
        $length = mb_strlen($message);

        if ($length <= self::SINGLE_MESSAGE_LENGTH) {
            return 1;
        }

        return (int) ceil($length / self::MULTI_MESSAGE_LENGTH);
    }

    /**
     * Validate GSM characters in message
     *
     * OLD SYSTEM: charPositionOrMinusOne() function
     * Checks for invalid characters (Microsoft Word characters, Apple apostrophes, etc.)
     *
     * @param string $message
     * @return array ['valid' => bool, 'error' => string|null, 'invalid_chars' => array]
     */
    public function validateGsmCharacters(string $message): array
    {
        $invalidChars = [];
        $invalidPositions = [];

        $length = mb_strlen($message);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($message, $i, 1);

            if (!$this->isValidGsmCharacter($char)) {
                $invalidChars[] = $char;
                $invalidPositions[] = $i + 1; // 1-based position
            }
        }

        if (!empty($invalidChars)) {
            $firstInvalidChar = $invalidChars[0];
            $firstPosition = $invalidPositions[0];

            return [
                'valid' => false,
                'error' => "The text for the message contains an invalid character: '{$firstInvalidChar}' at position {$firstPosition}. This is usually a Microsoft Word character or an Apple apostrophe, and will cause the message to fail if sent to the network. Try deleting the character now, and re-typing it.",
                'invalid_chars' => array_unique($invalidChars),
                'invalid_positions' => $invalidPositions
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'invalid_chars' => [],
            'invalid_positions' => []
        ];
    }

    /**
     * Check if a character is valid for GSM encoding
     *
     * @param string $char Single character
     * @return bool
     */
    public function isValidGsmCharacter(string $char): bool
    {
        // Check against GSM character set
        if (in_array($char, self::GSM_CHARS, true)) {
            return true;
        }

        // Also allow common Unicode characters that can be transliterated
        // These will be converted to GSM equivalents when sending
        $allowedUnicode = [
            "\t", // Tab (will be converted to space)
        ];

        if (in_array($char, $allowedUnicode, true)) {
            return true;
        }

        return false;
    }

    /**
     * Replace common invalid characters with GSM-compatible equivalents
     *
     * @param string $message
     * @return string Sanitized message
     */
    public function sanitizeMessage(string $message): string
    {
        // Common replacements for Microsoft Word and Apple characters
        // Using Unicode code points to avoid encoding issues

        // Smart quotes to regular quotes
        $message = str_replace("\u{201C}", '"', $message);  // Left double quotation mark "
        $message = str_replace("\u{201D}", '"', $message);  // Right double quotation mark "
        $message = str_replace("\u{2018}", "'", $message);  // Left single quotation mark '
        $message = str_replace("\u{2019}", "'", $message);  // Right single quotation mark '
        $message = str_replace('`', "'", $message);          // Grave accent

        // Dashes
        $message = str_replace("\u{2013}", '-', $message);  // En dash –
        $message = str_replace("\u{2014}", '-', $message);  // Em dash —

        // Ellipsis
        $message = str_replace("\u{2026}", '...', $message); // Ellipsis …

        // Other common replacements
        $message = str_replace("\u{2022}", '*', $message);   // Bullet point •
        $message = str_replace("\u{2122}", 'TM', $message);  // Trademark ™
        $message = str_replace("\u{00A9}", '(c)', $message); // Copyright ©
        $message = str_replace("\u{00AE}", '(R)', $message); // Registered ®

        // Tab to space
        $message = str_replace("\t", ' ', $message);

        return $message;
    }

    /**
     * Validate phone number format
     *
     * @param string $phoneNumber
     * @return array ['valid' => bool, 'error' => string|null, 'formatted' => string]
     */
    public function validatePhoneNumber(string $phoneNumber): array
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // Remove leading +
        $cleaned = ltrim($cleaned, '+');

        // Convert UK formats to international
        if (substr($cleaned, 0, 2) === '07') {
            $cleaned = '44' . substr($cleaned, 1);
        } elseif (substr($cleaned, 0, 4) === '0044') {
            $cleaned = substr($cleaned, 2);
        } elseif (substr($cleaned, 0, 1) === '7' && strlen($cleaned) === 10) {
            // Assume UK mobile if starts with 7 and is 10 digits
            $cleaned = '44' . $cleaned;
        }

        // Validate minimum length (at least country code + number)
        if (strlen($cleaned) < 8) {
            return [
                'valid' => false,
                'error' => "Phone number '{$phoneNumber}' is too short.",
                'formatted' => $cleaned
            ];
        }

        // Validate maximum length
        if (strlen($cleaned) > 15) {
            return [
                'valid' => false,
                'error' => "Phone number '{$phoneNumber}' is too long.",
                'formatted' => $cleaned
            ];
        }

        // Validate it contains only digits
        if (!ctype_digit($cleaned)) {
            return [
                'valid' => false,
                'error' => "Phone number '{$phoneNumber}' contains invalid characters.",
                'formatted' => $cleaned
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'formatted' => $cleaned
        ];
    }

    /**
     * Get message info (length, parts, cost estimate)
     *
     * @param string $message
     * @return array
     */
    public function getMessageInfo(string $message): array
    {
        $length = mb_strlen($message);
        $parts = $this->calculateMessageParts($message);

        $remainingInPart = $parts === 1
            ? self::SINGLE_MESSAGE_LENGTH - $length
            : self::MULTI_MESSAGE_LENGTH - ($length % self::MULTI_MESSAGE_LENGTH);

        if ($remainingInPart === self::MULTI_MESSAGE_LENGTH) {
            $remainingInPart = 0;
        }

        return [
            'length' => $length,
            'parts' => $parts,
            'remaining_in_current_part' => $remainingInPart,
            'max_length' => self::MAX_TOTAL_LENGTH,
            'is_multipart' => $parts > 1,
            'chars_per_part' => $parts > 1 ? self::MULTI_MESSAGE_LENGTH : self::SINGLE_MESSAGE_LENGTH
        ];
    }
}
