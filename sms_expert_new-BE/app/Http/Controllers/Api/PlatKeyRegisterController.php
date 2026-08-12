<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PlatinumLib;
use App\Models\User;

class PlatKeyRegisterController extends Controller
{
    protected $platinumLib;

    public function __construct(PlatinumLib $platinumLib)
    {
        $this->platinumLib = $platinumLib;
    }

    public function registerKeyword(Request $request)
    {
        // Validate required parameters
        $username = $request->input('usr');
        $password = $request->input('pwd');
        $keyword = $request->input('keyword');
        $shortcode = $request->input('shortcode');

        if (!$username || !$password) {
            return $this->platinumLib->apiReply("1|Missing usr or pwd parameter");
        }

        if (!$keyword || !$shortcode) {
            return $this->platinumLib->apiReply("1|Missing keyword or shortcode parameter");
        }

        // Log the request
        Log::info("Keyword registration request received.", [
            'usr' => $username,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);

        // Simulate user validation (Replace with real authentication logic)
        $userRef = $this->validateUser($username, $password);

        if (!$userRef) {
            return $this->platinumLib->apiReply("2|Invalid credentials");
        }

        // Validate keyword format
        if (!$this->validateKeywordChars($keyword)) {
            return $this->platinumLib->apiReply("3|Invalid keyword characters");
        }

        // Perform "whois" check
        $whoisResult = $this->platinumWhois($keyword, $shortcode);

        if (!$whoisResult['available']) {
            Log::info("Keyword not available.", ['usr' => $username, 'keyword' => $keyword]);
            return $this->platinumLib->apiReply($whoisResult['message']);
        }

        // Check wallet funds
        if (!$this->checkWalletFunds($userRef)) {
            return $this->platinumLib->apiReply("4|Insufficient funds");
        }

        // Register the keyword
        if (!$this->registerKeywordForUser($userRef, $keyword, $shortcode)) {
            return $this->platinumLib->apiReply("5|Keyword registration failed");
        }

        // Deduct the cost of the keyword from the wallet
        $this->chargeClient($userRef);

        // Update the user's status if necessary
        $this->updateUserStatus($userRef);

        // Notify support
        $this->notifySupport($userRef, $keyword, $shortcode);

        // Log and return success message
        Log::info("Keyword registration successful.", [
            'usr' => $username,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);

        return $this->platinumLib->apiReply("0|keyword creation successful");
    }

    private function validateUser($username, $password)
    {
        $user = User::where('uname', $username)->first();
        return $user && $user->pword === $password ? $user->bigid : null;
    }

    private function validateKeywordChars($keyword)
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $keyword);
    }

    private function platinumWhois($keyword, $shortcode)
    {
        // Simulate a "whois" check (Replace with actual implementation)
        $isAvailable = true; // Replace with real availability check
        $message = $isAvailable ? "Keyword available" : "Keyword not available";

        return [
            'available' => $isAvailable,
            'message' => $message,
        ];
    }

    private function checkWalletFunds($userRef)
    {
        // Simulate wallet funds check (Replace with real logic)
        return true; // Replace with actual fund verification
    }

    private function registerKeywordForUser($userRef, $keyword, $shortcode)
    {
        // Simulate keyword registration (Replace with actual implementation)
        return true; // Replace with database/API logic
    }

    private function chargeClient($userRef)
    {
        // Simulate charging the client (Replace with actual implementation)
        Log::info("Client charged successfully.", ['userRef' => $userRef]);
    }

    private function updateUserStatus($userRef)
    {
        // Simulate user status update
        Log::info("User status updated to 'freekey'.", ['userRef' => $userRef]);
    }

    private function notifySupport($userRef, $keyword, $shortcode)
    {
        // Simulate sending an email to support
        Log::info("Support notified about keyword registration.", [
            'userRef' => $userRef,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);
    }
}
