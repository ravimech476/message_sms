<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PlatinumLib;
use App\Models\User;

class PlatWhoIsController extends Controller
{
    protected $platinumLib;

    public function __construct(PlatinumLib $platinumLib)
    {
        $this->platinumLib = $platinumLib;
    }

    public function whoisKeyword(Request $request)
    {
        // Validate required parameters
        $username = $request->input('usr');
        $password = $request->input('pwd');
        $keyword = $request->input('keyword');
        $shortcode = $request->input('shortcode');

        if (!$username || !$password) {
            return $this->platinumLib->apiReply("1|Missing usr or pwd parameter");
        }

        if (!$keyword) {
            return $this->platinumLib->apiReply("2|Missing keyword parameter");
        }

        // Log the request
        Log::info("API Request received for WHOIS keyword.", [
            'usr' => $username,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);

        // Simulate user validation (Replace with real authentication logic)
        $userRef = $this->validateUser($username, $password);

        if (!$userRef) {
            return $this->platinumLib->apiReply("6|bad username or password");
        }

        // Validate keyword
        if (!$this->isValidKeyword($keyword)) {
            $msg = "4|Invalid keyword format";
            Log::warning($msg);
            return $this->platinumLib->apiReply($msg);
        }

        // Perform WHOIS check
        $response = $this->platinumLib->whoisKeyword($keyword, $shortcode);

        if (!$response || !isset($response['message'])) {
            $msg = "5|Error checking keyword availability";
            Log::error($msg);
            return $this->platinumLib->apiReply($msg);
        }

        // Log and return results
        $whoisResult = $response['message'];
        Log::info("WHOIS check completed successfully.", ['usr' => $username, 'result' => $whoisResult]);

        return $this->platinumLib->apiReply($whoisResult);
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

    private function isValidKeyword($keyword)
    {
        // Add your validation logic here (e.g., regex to allow only alphanumeric keywords)
        return preg_match('/^[a-zA-Z0-9]+$/', $keyword);
    }
}
