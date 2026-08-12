<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PlatinumLib;
use App\Models\User;

class PlatWalletController extends Controller
{

    protected $platinumLib;

    public function __construct(PlatinumLib $platinumLib)
    {
        $this->platinumLib = $platinumLib;
    }

    public function getWallet(Request $request)
    {
        // Validate required parameters
        $username = $request->input('usr');
        $password = $request->input('pwd');

        if (!$username || !$password) {
            return $this->platinumLib->apiReply("1|Missing usr or pwd parameter");
        }

        // Log the request
        Log::info("API Request received for wallet.", ['usr' => $username]);

        // Validate user credentials
        $userRef = $this->validateUser($username, $password);

        if (!$userRef) {
            return $this->platinumLib->apiReply("2|Invalid credentials");
        }

        // Fetch wallet details
        $walletDetails = $this->getWalletValues($userRef);

        if (!$walletDetails['success']) {
            return $this->platinumLib->apiReply("3|Failed to retrieve wallet details");
        }

        // Log and return the results
        Log::info("Wallet details fetched successfully.", ['usr' => $username, 'wallet' => $walletDetails['data']]);
        return $this->platinumLib->apiReply($walletDetails['data']);
    }

    private function validateUser($username, $password)
    {
        // Query the User model for the username
        $user = User::where('uname', $username)->first();

        // Check if user exists and password matches
        if ($user && $user->pword === $password) {
            return $user->bigid; // Return the user's bigid or other identifier
        }

        // Return null if validation fails
        return null;
    }

    private function getWalletValues($userRef)
    {
        try {
            // Replace this with an actual query to your database
            $user = User::where('bigid', $userRef)->first();

            if (!$user) {
                return ['success' => false];
            }

            $smsBought = sprintf("%01.4f", $user->smsg_wallet);
            $smsSent = sprintf("%01.4f", $user->smsg_server1_sent + $user->smsg_server2_sent);
            $walletLoan = sprintf("%01.4f", $user->walletloan);
            $keywordWallet = sprintf("%01.0f", $user->platkeywordwallet);
            $keywordCost = sprintf("%01.0f", $user->platkeywordcost);

            $smsBoughtWithoutLoan = sprintf("%01.4f", $smsBought - $walletLoan);
            $realCashPosition = sprintf("%01.4f", $smsBoughtWithoutLoan - $smsSent);
            $smsAvailableWithLoan = sprintf("%01.4f", $smsBought - $smsSent);

            $apiReply = "0|smsbought=$smsBoughtWithoutLoan;smssent=$smsSent;smsavailableincloan=$smsAvailableWithLoan;walletloan=$walletLoan;realcashposition=$realCashPosition;platkeywordwallet=$keywordWallet;platkeywordcost=$keywordCost";

            return ['success' => true, 'data' => $apiReply];
        } catch (\Exception $e) {
            Log::error("Error fetching wallet details: " . $e->getMessage());
            return ['success' => false];
        }
    }
}
