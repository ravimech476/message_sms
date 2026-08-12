<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ApiController extends Controller {

    public function __construct() {
        parent::__construct(); // Ensure the base controller constructor is called
    }

    public function login(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                        'userName' => 'required',
                        'password' => 'required'
            ]);
            if ($validate->fails()) {
                return response()->json(['status' => false, 'message' => 'validation Error', 'errors' => $validate->errors()], 403);
            }
            $user = User::where('uname', $request->userName)->first();

            if (($user) && ($user->pword === $request->password)) {
                Auth::login($user);
                return response()->json(['status' => true, 'message' => 'User logged in successfully', 'token' => $user->createToken('API TOKEN')->plainTextToken], 200);
            } else {
                return response()->json(['status' => false, 'message' => 'User name and Password does not match with our record'], 401);
            }
        } catch (\Throwable $ex) {
            
        }
    }

    public function register(Request $request) {
        try {
            $validate = Validator::make($request->all(), [
                        'name' => 'required',
                        'businessName' => 'required',
                        'contactEmailId' => 'required|email|unique:users,contactemail',
                        'phoneNumber' => 'required',
            ]);
            if ($validate->fails()) {
                return response()->json(['status' => false, 'message' => 'validation Error', 'errors' => $validate->errors()], 403);
            }
            $bigId = generateCustomString();
            $time = time();
            $user = User::create([
                        'bigid' => $bigId,
                        'first_ip' => $request->ip(),
                        'busname' => $request->businessName,
                        'uname' => getUserName($bigId, 8),
                        'pword' => getPassword($bigId, 8),
                        'contactname' => $request->name,
                        'phone' => $request->phone,
                        'contactemail' => $request->contactEmailId,
                        'datefrozen' => $time,
                        'mobilenumber' => $request->phoneNumber,
                        'smsg_wallet' => getsmsExpertDefaultWalletPrice(),
                        'user_type' => getUserType(),
                        'masteruname' => getUserName($bigId, 8),
                        'nfckey' => getUserName($bigId, 6),
                        'customer_type' => getCustomerType(),
                        'introducerref' => 'N/A',
                        'meetlocation' => 'N/A',
                        'itaggsecretkey' => 'N/A',
                        'badhlrlastwarned' => 'N/A',
                        'shopify_cust_id' => 'N/A',
                        'anondesc' => 'N/A',
                        'custtype' => 'N/A',
                        'bulksms_tally_last_reset' => date("Y-m-d H:i:s")
            ]);
            return response()->json(['status' => true, 'message' => 'User created successfully ', 'token' => $user->createToken('API TOKEN')->plainTextToken], 200);
        } catch (\Throwable $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage()], 500);
        }
    }

    public function getUserProfileDetails(Request $request) {
        try {
            $authDetails = auth()->user();
            return response()->json(['status' => true, 'message' => 'User Details', 'data' => $authDetails], 200);
        } catch (\Throwable $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage()], 500);
        }
    }

    public function logout(Request $request) {
        try {
//            auth()->user()->tokens()->delete(); //delete all token
            $request->user()->currentAccessToken()->delete(); //delete  current token
            return response()->json(['status' => true, 'message' => 'User Logout successfully', 'data' => []], 200);
        } catch (\Throwable $ex) {
            return response()->json(['status' => false, 'message' => $ex->getMessage()], 500);
        }
    }

}
