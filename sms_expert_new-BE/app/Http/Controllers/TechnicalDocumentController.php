<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\User;

class TechnicalDocumentController extends Controller
{
    public function index()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.index', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function wholeSaleWalletCheck()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.wallet_check', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function sendingSMS()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.sending_sms', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function receivingdLRS()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.receivinglrs', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function receivingSMS()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.receivingsms', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function keywordwhoIs()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.keywordwhois', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function keywordRegistration()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.keywordregistration', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function keywordsetForwardings()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.keywordsetforwardings', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function listkeyWords()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.listkeywords', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function keywordRenewal()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.keywordrenewal', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function keywordDeletion()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.keyworddeletion', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function keywordReplacement()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.keywordreplacement', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function wholesaleapiResponseCodes()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::where('bigid', $bigid)->first();
            return view('customer.technical.wholesaleapiresponsecodes', compact('user'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }
}
