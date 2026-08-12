<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PlatinumLib;
use App\Models\User;

class PlatKeySetForwardController extends Controller
{
    protected $platinumLib;

    public function __construct(PlatinumLib $platinumLib)
    {
        $this->platinumLib = $platinumLib;
    }

    public function setKeywordForwardings(Request $request)
    {
        // Validate required parameters
        $username = $request->input('usr');
        $password = $request->input('pwd');
        $keyword = $request->input('keyword');
        $shortcode = $request->input('shortcode', '');
        $setsmsurl = $request->input('setsmsurl');
        $setsmsemail = $request->input('setsmsemail');
        $newsmsurl = $request->input('newsmsurl', '');
        $newsmsemail = $request->input('newsmsemail', '');

        if (!$username || !$password || !$keyword) {
            return $this->platinumLib->apiReply("1|Missing usr, pwd, or keyword parameter");
        }

        // Log the request
        Log::info("API Request received for setting keyword forwardings.", ['usr' => $username, 'keyword' => $keyword]);

        // Validate user
        $userRef = $this->validateUser($username, $password);
        if (!$userRef) {
            return $this->platinumLib->apiReply("2|Invalid credentials");
        }

        // Validate additional parameters
        if (!in_array($setsmsurl, ['y', 'n']) || !in_array($setsmsemail, ['y', 'n'])) {
            return $this->platinumLib->apiReply("3|Invalid forwarding flags");
        }

        if ($setsmsurl === 'n' && $setsmsemail === 'n') {
            return $this->platinumLib->apiReply("4|No forwarding changes to do");
        }

        // Log forwarding parameters
        Log::info("Forwarding parameters:", [
            'setsmsurl' => $setsmsurl,
            'setsmsemail' => $setsmsemail,
            'newsmsurl' => $newsmsurl,
            'newsmsemail' => $newsmsemail,
            'shortcode' => $shortcode,
        ]);

        // Update forwardings
        $updateResult = $this->updateForwardings($userRef, $keyword, $setsmsurl, $setsmsemail, $newsmsurl, $newsmsemail, $shortcode);

        if (!$updateResult) {
            return $this->platinumLib->apiReply("5|Forwarding updates failed");
        }

        // Notify support
        $this->notifySupport($userRef, "Keyword SetForwardings", $keyword, $shortcode);

        // Log and return success
        Log::info("Forwarding settings updated successfully.", ['usr' => $username, 'keyword' => $keyword]);
        return $this->platinumLib->apiReply("33|bad keyword sms/mms email/url forwarding flags");
    }

    private function validateUser($username, $password)
    {
        $user = User::where('uname', $username)->first();
        if ($user && $user->pword === $password) {
            return $user->bigid;
        }
        return null;
    }

    private function updateForwardings($userRef, $keyword, $setsmsurl, $setsmsemail, $newsmsurl, $newsmsemail, $shortcode)
    {
        // Replace with logic to update forwardings in the database or external API
        // Simulating successful update for now
        Log::info("Updating forwardings in the database or external API.", [
            'userRef' => $userRef,
            'keyword' => $keyword,
            'setsmsurl' => $setsmsurl,
            'setsmsemail' => $setsmsemail,
            'newsmsurl' => $newsmsurl,
            'newsmsemail' => $newsmsemail,
            'shortcode' => $shortcode,
        ]);

        return true; // Simulate success
    }

    private function notifySupport($userRef, $action, $keyword, $shortcode)
    {
        // Simulate sending an email to support
        Log::info("Notifying support about action.", [
            'userRef' => $userRef,
            'action' => $action,
            'keyword' => $keyword,
            'shortcode' => $shortcode,
        ]);
    }
}
