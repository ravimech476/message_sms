<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ItaggInstance;


class InstanceController extends Controller
{

    public function fetchKeywordDetails(Request $request)
    {
        $keyword = ItaggInstance::find($request->keyword_id);

        if (!$keyword) {
            return response('<p class="text-danger">Keyword not found.</p>', 404);
        }

        $keywordText = e($keyword->keyword ?? 'No keyword');
        // $number = e($keyword->number ?? 'Unknown Number');
        $expiry = $keyword->expiry ? date("jS F Y", strtotime($keyword->expiry)) : 'Unknown';
        $keyLevel = $keyword->keylevel ?? 0;
        $keyLevelStr = "KEY LEVEL " . $keyLevel;
        $itaggId = $keyword->id;

        $html = '<p class="text">';
        $html .= '<big><strong>iTAGG Summary</strong></big><br>';
        // $html .= '"' . $keywordText . '" on ' . $number . '<br>';
        $html .= '"' . $keywordText . '"' . '<br>';
        $html .= 'Expires ' . $expiry . '<br>';
        $html .= '<font color="#cc0000">' . $keyLevelStr . '</font><br>';
        $html .= 'itagg_instance.id = ' . $itaggId . '<br>';
        if ($keyword->active == 1) {
            $html .= '<font color="green">This iTAGG is currently Active</font><br>';
            $html .= '<input onclick="itagg_suspend()" style="cursor:pointer" type="button" name="btnItaggSuspend" value="Suspend this iTAGG"><br>';
        } else {
            $html .= '<font color="#cc0000">This iTAGG is currently Inactive</font><br>';
            $html .= '<input onclick="itagg_activate()" style="cursor:pointer" type="button" name="btnItaggActivate" value="Activate this iTAGG"><br>';
        }
        $html .= '</p>';

        return $html;
    }
}
