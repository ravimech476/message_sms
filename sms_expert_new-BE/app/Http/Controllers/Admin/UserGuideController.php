<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the staff-only user guides (User Guide hub, Admin Guide, Migration
 * Guide, Performance & Benchmark reports) and their screenshots through the
 * admin-authenticated route group. The source files live OUTSIDE public/
 * (in resources/userguides/) so they can never be fetched as static files
 * without a valid admin session.
 */
class UserGuideController extends Controller
{
    /** Guides that may be served, mapped to their source file name. */
    private const GUIDES = [
        'index'       => 'userguide.html',
        'admin'       => 'userguide-admin.html',
        'migration'   => 'userguide-migration.html',
        'performance' => 'performance-report.html',
        'benchmark'   => 'benchmark-report.html',
    ];

    /**
     * Original .html file names as they appear in the guides' internal links,
     * mapped to the guide key they should now route to. Used so back/next
     * navigation between guides stays inside the authenticated routes instead
     * of pointing at the removed public .html files.
     */
    private const INTERNAL_LINKS = [
        'userguide.html'           => 'index',
        'userguide-admin.html'     => 'admin',
        'userguide-migration.html' => 'migration',
        'performance-report.html'  => 'performance',
        'benchmark-report.html'    => 'benchmark',
    ];

    /**
     * Customer-facing guides — hub links to these point at the customer-protected
     * route (they require a customer session, not an admin one).
     */
    private const CUSTOMER_LINKS = [
        'userguide-dashboard.html' => 'dashboard',
        'userguide-campaign.html'  => 'campaign',
    ];

    /**
     * Render a protected guide, rewriting screenshot sources and internal
     * navigation links so everything resolves through the admin routes.
     */
    public function show(string $guide): Response
    {
        abort_unless(isset(self::GUIDES[$guide]), 404);

        $path = resource_path('userguides/' . self::GUIDES[$guide]);
        abort_unless(File::exists($path), 404);

        $html = File::get($path);

        // Point image sources (userguide-screenshots/... or ../userguide-screenshots/...)
        // at the authenticated screenshot route instead of the removed public folder.
        $html = preg_replace('#(?:\.\./)*userguide-screenshots/#', url('/admin/userguide-screenshots/') . '/', $html);

        // Rewrite every internal .html link (back buttons, cross-links) to the
        // correct destination, preserving any #anchor / ?query.
        $html = $this->rewriteInternalLinks($html);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Serve a guide screenshot from outside public/, gated by the admin group.
     */
    public function screenshot(string $file): BinaryFileResponse
    {
        // Hard filename allow-list: basename only, png only, no path traversal.
        abort_unless(preg_match('/^[A-Za-z0-9\-_]+\.png$/', $file) === 1, 404);

        $path = resource_path('userguides/screenshots/' . $file);
        abort_unless(File::exists($path), 404);

        return response()->file($path);
    }

    /**
     * Replace hard-coded .html guide links inside the served HTML with their
     * proper URLs (protected route for staff guides, public URL for the
     * customer guides), keeping any fragment/query string intact.
     */
    private function rewriteInternalLinks(string $html): string
    {
        return preg_replace_callback('/href="([^"]+)"/i', function (array $m): string {
            $href = $m[1];

            // Split off #fragment / ?query so we can preserve it.
            $suffix = '';
            $pathPart = $href;
            if (preg_match('/^([^#?]*)([#?].*)$/', $href, $parts)) {
                $pathPart = $parts[1];
                $suffix = $parts[2];
            }

            $base = basename($pathPart);

            if (isset(self::INTERNAL_LINKS[$base])) {
                return 'href="' . route('admin.userguide.show', self::INTERNAL_LINKS[$base]) . $suffix . '"';
            }

            if (isset(self::CUSTOMER_LINKS[$base])) {
                return 'href="' . route('customer.userguide.show', self::CUSTOMER_LINKS[$base]) . $suffix . '"';
            }

            return $m[0];
        }, $html);
    }
}
