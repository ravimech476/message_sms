<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PlatinumLib;
use App\Models\User;

class PlatKeyReplaceController extends Controller
{
    protected $platinumLib;

    public function __construct(PlatinumLib $platinumLib)
    {
        $this->platinumLib = $platinumLib;
    }

    public function replaceKeyword(Request $request)
    {
        // Step 1: Validate main parameters
        $username = $request->input('usr');
        $password = $request->input('pwd');
        $oldKeyword = $request->input('oldkeyword');
        $newKeyword = $request->input('newkeyword');
        $oldShortcode = $request->input('oldshortcode');
        $newShortcode = $request->input('newshortcode');

        if (!$username || !$password) {
            return $this->respondWithError("1|Missing usr or pwd parameter");
        }

        if (!$oldKeyword || !$newKeyword || !$oldShortcode || !$newShortcode) {
            return $this->respondWithError("2|Missing required keyword or shortcode parameters");
        }

        // Step 2: Log the request
        Log::info("API Request received for keyword replacement.", [
            'usr' => $username,
            'oldKeyword' => $oldKeyword,
            'newKeyword' => $newKeyword,
            'oldShortcode' => $oldShortcode,
            'newShortcode' => $newShortcode,
        ]);

        // Step 3: Validate the user
        $userRef = $this->validateUser($username, $password);
        if (!$userRef) {
            return $this->respondWithError("6|bad username or password");
        }

        // Step 4: Validate keyword characters
        if (!$this->isValidKeyword($oldKeyword) || !$this->isValidKeyword($newKeyword)) {
            return $this->respondWithError("4|Invalid characters in keyword");
        }

        // Step 5: Check if the new keyword is available
        $availabilityResponse = $this->checkKeywordAvailability($newKeyword, $newShortcode);
        if (!$availabilityResponse['isAvailable']) {
            Log::warning("Keyword not available.", [
                'keyword' => $newKeyword,
                'shortcode' => $newShortcode,
                'response' => $availabilityResponse['message'],
            ]);
            return $this->respondWithError($availabilityResponse['message']);
        }

        // Step 6: Replace the keyword
        if (!$this->replaceKeywordAction($userRef, $oldKeyword, $newKeyword, $oldShortcode, $newShortcode)) {
            return $this->respondWithError("5|Keyword replacement failed");
        }

        // Step 7: Update user type if necessary
        $this->updateUserType($userRef);

        // Step 8: Notify support
        $this->notifySupport($userRef, "Keyword Replace", $oldKeyword, $oldShortcode, $newKeyword, $newShortcode);

        // Step 9: Log success and respond
        Log::info("Keyword replaced successfully.", [
            'usr' => $username,
            'oldKeyword' => $oldKeyword,
            'newKeyword' => $newKeyword,
        ]);

        return $this->platinumLib->apiReply("6|Keyword replacement successful");
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

    private function checkKeywordAvailability($keyword, $shortcode)
    {
        // Simulate external keyword availability check
        $response = $this->platinumLib->whoisKeyword($keyword, $shortcode); // Replace with actual API call logic

        // Parse the response and return
        return [
            'isAvailable' => $response['isAvailable'] ?? false,
            'message' => $response['message'] ?? "Keyword not available",
        ];
    }

    private function replaceKeywordAction($userRef, $oldKeyword, $newKeyword, $oldShortcode, $newShortcode)
    {
        // Simulate an external API call for keyword replacement
        $url = "https://secure.devsmsexpert.com/smsg/plat_keyreplace.mes";
        $queryParams = http_build_query([
            'usr' => $userRef,
            'oldkeyword' => $oldKeyword,
            'newkeyword' => $newKeyword,
            'oldshortcode' => $oldShortcode,
            'newshortcode' => $newShortcode,
        ]);

        $response = file_get_contents("{$url}?{$queryParams}");

        // Log API response
        Log::info("API Response for keyword replacement:", [
            'response' => $response,
        ]);

        // Simulate response handling (replace with actual API response check)
        return $response === "success";
    }

    private function updateUserType($userRef)
    {
        // Placeholder for updating the user's type
        Log::info("User type updated to 'freekey' if applicable.", ['userRef' => $userRef]);
        // Implement your actual logic here if needed
    }

    private function notifySupport($userRef, $action, $oldKeyword, $oldShortcode, $newKeyword, $newShortcode)
    {
        Log::info("Notifying support.", [
            'userRef' => $userRef,
            'action' => $action,
            'oldKeyword' => $oldKeyword,
            'oldShortcode' => $oldShortcode,
            'newKeyword' => $newKeyword,
            'newShortcode' => $newShortcode,
        ]);

        // Placeholder for sending support email
        // Implement your actual email-sending logic here
    }

    private function respondWithError($message)
    {
        Log::error($message);
        return $this->platinumLib->apiReply($message);
    }
}
