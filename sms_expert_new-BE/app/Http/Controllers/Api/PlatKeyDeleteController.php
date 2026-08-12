<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PlatinumLib;
use App\Models\User;

class PlatKeyDeleteController extends Controller
{
    protected $platinumLib;

    public function __construct(PlatinumLib $platinumLib)
    {
        $this->platinumLib = $platinumLib;
    }

    public function deleteKeyword(Request $request)
    {
        // Step 1: Validate required parameters
        $username = $request->input('usr');
        $password = $request->input('pwd');
        $keyword = $request->input('keyword');
        $shortcode = $request->input('shortcode');

        if (!$username || !$password) {
            return $this->respondWithError("1|Missing usr or pwd parameter");
        }

        if (!$keyword || !$shortcode) {
            return $this->respondWithError("2|Missing keyword or shortcode parameter");
        }

        // Step 2: Log the request
        Log::info("API Request received for keyword deletion.", [
            'usr' => $username,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);

        // Step 3: Validate the user
        $userRef = $this->validateUser($username, $password);
        if (!$userRef) {
            return $this->respondWithError("6|bad username or password");
        }

        // Step 4: Validate keyword characters
        if (!$this->isValidKeyword($keyword)) {
            return $this->respondWithError("4|Invalid characters in keyword");
        }

        // Step 5: Attempt keyword deletion
        if (!$this->deleteKeywordAction($userRef, $keyword, $shortcode)) {
            return $this->respondWithError("5|Keyword deletion failed");
        }

        // Step 6: Notify support
        $this->notifySupport($userRef, "Keyword Deletion", $keyword, $shortcode);

        // Step 7: Respond with success
        Log::info("Keyword deleted successfully.", [
            'usr' => $username,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);

        return $this->platinumLib->apiReply("6|Keyword deletion successful");
    }

    private function validateUser($username, $password)
    {
        // Query the User model for the username
        $user = User::where('uname', $username)->first();
        return $user && $user->pword === $password ? $user->bigid : null;
    }

    private function isValidKeyword($keyword)
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $keyword);
    }

    private function deleteKeywordAction($userRef, $keyword, $shortcode)
    {
        // Simulate a call to an external API for keyword deletion
        $url = "https://secure.devsmsexpert.com/smsg/plat_keydel.mes";
        $queryParams = http_build_query([
            'usr' => $userRef,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);

        $response = file_get_contents("{$url}?{$queryParams}");

        // Log API response
        Log::info("API Response for keyword deletion:", [
            'response' => $response,
        ]);

        // Simulate response handling (replace with actual API logic)
        return $response === "success";
    }

    private function notifySupport($userRef, $action, $keyword, $shortcode)
    {
        // Log email notification
        Log::info("Notifying support.", [
            'userRef' => $userRef,
            'action' => $action,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);

        // Placeholder for sending support email
        // Implement your actual email logic here
    }

    private function respondWithError($message)
    {
        Log::error($message);
        return $this->platinumLib->apiReply($message);
    }
}
