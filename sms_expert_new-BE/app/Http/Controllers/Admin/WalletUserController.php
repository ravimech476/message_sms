<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class WalletUserController extends Controller
{
    public function updateWallet(Request $request, $id)
    {
        $request->validate([
            'smsg_wallet' => 'required|numeric|between:0,999999999999.999999',
        ]);

        $walletUser = User::findOrFail($id);
        $walletUser->smsg_wallet = $request->smsg_wallet;
        $walletUser->save();

        session()->flash('activeTab', 'customer-wallet-balance');

        return redirect()->route('admin.user.show', ['id' => $walletUser->id])
            ->with('success', 'Wallet balance updated successfully.');
    }

    public function updateWalletLoan(Request $request, $id)
    {
        $request->validate([
            'walletloan' => 'required|numeric',
            'bulk_throughput' => 'required|numeric',
        ]);

        $user = User::findOrFail($id);
        $user->walletloan = $request->walletloan;
        $user->bulk_throughput = $request->bulk_throughput;
        $user->save();

        session()->flash('activeTab', 'customer-wallet-balance');

        return redirect()->route('admin.user.show', ['id' => $user->id])
            ->with('success', 'Loan limit updated successfully');
    }

    public function updateWalletPlat(Request $request, $id)
    {
        $request->validate([
            'platinumaccess' => 'required|in:y,n',
            'platkeywordwallet' => 'required|numeric',
        ]);

        $user = User::findOrFail($id);
        $user->platinumaccess = $request->platinumaccess;
        $user->platkeywordwallet = $request->platkeywordwallet;
        $user->save();

        session()->flash('activeTab', 'customer-wallet-balance');

        return redirect()->route('admin.user.show', ['id' => $user->id])
            ->with('success', 'Platinum access updated successfully');
    }
}
