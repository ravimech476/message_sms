<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PlatinumLib;

class PlatGetKeysController extends Controller
{

    protected $platinumLib;

    public function __construct(PlatinumLib $platinumLib)
    {
        $this->platinumLib = $platinumLib;
    }

    public function getKeywords(Request $request)
    {
        // Validate required parameters
        $username = $request->input('usr');
        $password = $request->input('pwd');

        if (!$username || !$password) {
            return $this->platinumLib->apiReply("1|Missing usr or pwd parameter");
        }

        // Log the request
        Log::info("API Request received for keywords.", ['usr' => $username]);

        // Simulate user validation (Replace with real authentication logic)
        $userRef = $this->validateUser($username, $password);
        if (!$userRef) {
            return $this->platinumLib->apiReply("2|Invalid credentials");
        }

        // Fetch keywords for the user
        $keywords = $this->getKeywordsForUser($userRef);
        if (empty($keywords)) {
            return $this->platinumLib->apiReply("3|No keywords found");
        }

        // Log and return the results
        $keywordsString = implode("\n", $keywords);
        Log::info("Keywords fetched successfully.", ['usr' => $username, 'keywords' => $keywordsString]);

        return $this->platinumLib->apiReply($keywordsString);
    }

    private function validateUser($username, $password)
    {
        // Replace this with actual user validation logic, e.g., querying the database
        // For now, simulate successful validation
        return $username === 'master' && $password === '12345' ? 1 : null;
    }

    private function getKeywordsForUser($userRef)
    {
        // Replace this with actual logic to fetch keywords from the database or an external API
        // Simulated keyword data
        return [
            "keyword1|description1",
            "keyword2|description2",
        ];
    }

    // private function apiReply($message)
    // {
    //     // Return the message as a plain text response
    //     return response($message, 200)->header('Content-Type', 'text/plain');
    // }
}
