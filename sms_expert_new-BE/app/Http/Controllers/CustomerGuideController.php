<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the customer-facing user guides (Dashboard Guide, Campaign Guide) and
 * their screenshots through the customer-authenticated route group. The source
 * files live OUTSIDE public/ (in resources/userguides/) so they can never be
 * fetched as static files without a valid customer session.
 */
class CustomerGuideController extends Controller
{
    /** Guides that may be served, mapped to their source file name. */
    private const GUIDES = [
        'dashboard' => 'userguide-dashboard.html',
        'campaign'  => 'userguide-campaign.html',
    ];

    /**
     * Render a protected customer guide, rewriting screenshot sources and the
     * "Back to User Guide" link so they resolve through the customer area.
     */
    public function show(string $guide): Response
    {
        abort_unless(isset(self::GUIDES[$guide]), 404);

        $path = resource_path('userguides/' . self::GUIDES[$guide]);
        abort_unless(File::exists($path), 404);

        $html = File::get($path);

        // Point image sources at the authenticated screenshot route.
        $html = preg_replace('#(?:\.\./)*userguide-screenshots/#', url('/customer/userguide-screenshots/') . '/', $html);

        // The guides' back button targets the (now admin-only) userguide.html hub,
        // which isn't appropriate for customers — send them to their dashboard.
        $html = preg_replace_callback('/href="([^"]+)"/i', function (array $m): string {
            $path = preg_replace('/[#?].*$/', '', $m[1]);
            return basename($path) === 'userguide.html'
                ? 'href="' . route('dashboard') . '"'
                : $m[0];
        }, $html);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Serve a guide screenshot from outside public/, gated by the customer group.
     */
    public function screenshot(string $file): BinaryFileResponse
    {
        // Hard filename allow-list: basename only, png only, no path traversal.
        abort_unless(preg_match('/^[A-Za-z0-9\-_]+\.png$/', $file) === 1, 404);

        $path = resource_path('userguides/screenshots/' . $file);
        abort_unless(File::exists($path), 404);

        return response()->file($path);
    }
}
