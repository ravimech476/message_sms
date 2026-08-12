<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PlatinumLib;
use App\Models\User;

class PlatKeyRenewController extends Controller
{
    protected $platinumLib;

    public function __construct(PlatinumLib $platinumLib)
    {
        $this->platinumLib = $platinumLib;
    }

    public function renewKeyword(Request $request)
    {
        $username = $request->input('usr');
        $password = $request->input('pwd');
        $keyword = $request->input('keyword');
        $shortcode = $request->input('shortcode');

        // Step 1: Validate and log main parameters
        if (!$username || !$password) {
            return $this->platinumLib->apiReply("1|Missing usr or pwd parameter");
        }

        Log::info("Keyword renewal request received.", ['usr' => $username]);

        // Step 2: Validate extra parameters
        if (!$keyword || !$shortcode) {
            return $this->platinumLib->apiReply("2|Missing keyword or shortcode parameter");
        }

        $this->checkValidChars($keyword);
        Log::info("Validated keyword and shortcode.", ['keyword' => $keyword, 'shortcode' => $shortcode]);

        // Step 3: Authenticate the user
        $userRef = $this->validateUser($username, $password);
        if (!$userRef) {
            return $this->platinumLib->apiReply("6|bad username or password");
        }

        // Step 4: Check wallet funds
        if (!$this->checkWalletFunds($userRef)) {
            return $this->platinumLib->apiReply("4|Insufficient funds");
        }

        // Step 5: Attempt to renew the keyword
        if (!$this->renewKeywordAction($userRef, $keyword, $shortcode)) {
            return $this->platinumLib->apiReply("5|Keyword renewal failed");
        }

        // Step 6: Deduct the cost of renewal from the client's wallet
        $this->chargeClient($userRef);

        // Step 7: Update user type if needed
        $this->moveUserFromLegacyToFreeKey($userRef);

        // Step 8: Log and return updated wallet values
        $walletValues = $this->getWalletValues($userRef);
        $this->notifySupport($userRef, "Keyword Renew", $keyword, $shortcode);

         // Step 9:Values
        $walletValues = $this->getWalletValues($userRef);
        $this->notifySupport($userRef, "Keyword failed", $keyword, $shortcode);

        return $this->platinumLib->apiReply("6|Keyword renewal successful");
    }

    private function validateUser($username, $password)
    {
        $user = User::where('uname', $username)->first();
        return $user && $user->pword === $password ? $user->bigid : null;
    }

    private function checkValidChars($input)
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $input)) {
            return $this->platinumLib->apiReply("7|Invalid characters in input");
            // $this->logAndExit("Invalid characters in input", "7|Invalid characters in input");
        }
    }

    private function checkWalletFunds($userRef)
    {
        // Replace with actual wallet fund check logic
        return true;
    }

    private function renewKeywordAction($userRef, $keyword, $shortcode)
    {
        // Simulate keyword renewal logic
        return false;
    }

    private function chargeClient($userRef)
    {
        // Replace with logic to deduct funds from the user's wallet
        Log::info("Charged client for keyword renewal.", ['userRef' => $userRef]);
    }

    private function moveUserFromLegacyToFreeKey($userRef)
    {
        // Replace with logic to update the user's status
        Log::info("Moved user from legacy to freekey status if applicable.", ['userRef' => $userRef]);
    }

    private function getWalletValues($userRef)
    {
        // Replace with logic to fetch updated wallet values
        return [];
    }

    private function notifySupport($userRef, $action, $keyword, $shortcode)
    {
        // Replace with logic to email support
        Log::info("Support notified for action.", [
            'userRef' => $userRef,
            'action' => $action,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);
    }

    private function logAndExit($logMessage, $responseMessage)
    {
        Log::info($logMessage);
        exit($this->platinumLib->apiReply($responseMessage));
    }
}
